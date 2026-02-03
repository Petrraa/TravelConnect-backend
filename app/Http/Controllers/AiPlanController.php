<?php

namespace App\Http\Controllers;

use App\Models\AiGeneration;
use App\Models\Trip;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use Illuminate\Support\Facades\DB;


class AiPlanController extends Controller
{
    public function generate(Request $request)
    {
        $data = $request->validate([
            'destination' => ['required','string','max:255'],
            'days' => ['required','integer','min:1','max:21'],
            'budget' => ['nullable','numeric','min:0'],
            'pace' => ['nullable','in:lagano,normalno,brzo'],
            'interests' => ['nullable','array'],
            'interests.*' => ['string','max:50'],
            'trip_id' => ['nullable','integer','exists:trips,id'],
        ]);

        $trip = null;
        if (!empty($data['trip_id'])) {
            $trip = Trip::find($data['trip_id']);
            if ($trip && $trip->user_id !== $request->user()->id) {
                abort(403, 'Ne možeš generisati plan za tuđe putovanje.');
            }
        }

        $promptJson = [
            'destination' => $data['destination'],
            'days' => (int)$data['days'],
            'budget' => $data['budget'] ?? null,
            'pace' => $data['pace'] ?? 'normalno',
            'interests' => $data['interests'] ?? [],
        ];

        $system = "Ti si travel planner. Vrati ISKLJUČIVO validan JSON bez dodatnog teksta.";
        $user = "Napravi plan putovanja kao JSON sa ovom strukturom:
{
  \"title\": string,
  \"description\": string,  
  \"summary\": string,
  \"tips\": string[],
  \"days\": [
    {
      \"day\": number,
      \"title\": string,
      \"items\": [
        {\"type\":\"activity|food|transport|hotel\",\"time\":\"HH:MM\",\"title\":string,\"location\":string|null,\"notes\":string|null}
      ]
    }
  ]
}
Vrati tačno {$promptJson['days']} dana.
Ulazni podaci: " . json_encode($promptJson, JSON_UNESCAPED_UNICODE);

        $response = OpenAI::responses()->create([
            'model' => 'gpt-4.1-mini',
            'input' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ]);

        $text = $response->output_text ?? null;

        if (!$text) {
            $text = $this->tryExtractText($response);
        }

        $plan = json_decode($text, true);
        if (!is_array($plan)) {
            abort(422, "AI nije vratio validan JSON. Probaj opet.");
        }

        $log = AiGeneration::create([
            'user_id' => $request->user()->id,
            'trip_id' => $trip?->id,
            'prompt_json' => $promptJson,
            'result_json' => $plan,
            'model' => 'gpt-4.1-mini',
        ]);

        return response()->json([
            'message' => 'Plan generisan.',
            'ai_generation_id' => $log->id,
            'plan' => $plan,
        ], 201);
    }

    private function tryExtractText($response): ?string
    {
        if (!isset($response->output) || !is_array($response->output)) return null;

        $chunks = [];
        foreach ($response->output as $out) {
            if (!isset($out->content) || !is_array($out->content)) continue;
            foreach ($out->content as $c) {
                if (isset($c->text)) $chunks[] = $c->text;
            }
        }
        $joined = trim(implode("\n", $chunks));
        return $joined !== '' ? $joined : null;
    }
    public function planAndApply(Request $request)
{
    $data = $request->validate([
        'trip_id' => ['required','integer','exists:trips,id'],

        'destination' => ['required','string','max:255'],
        'days' => ['required','integer','min:1','max:21'],
        'budget' => ['nullable','numeric','min:0'],
        'pace' => ['nullable','in:lagano,normalno,brzo'],
        'interests' => ['nullable','array'],
        'interests.*' => ['string','max:50'],

        'replace' => ['nullable','boolean'],
    ]);

    $trip = Trip::findOrFail($data['trip_id']);

    if ($trip->user_id !== $request->user()->id) {
        abort(403, 'Ne možeš primijeniti AI plan na tuđe putovanje.');
    }

    $replace = $data['replace'] ?? true;


    $promptJson = [
        'destination' => $data['destination'],
        'days' => (int)$data['days'],
        'budget' => $data['budget'] ?? null,
        'pace' => $data['pace'] ?? 'normalno',
        'interests' => $data['interests'] ?? [],
    ];

    $plan = $this->generatePlanFromOpenAI($promptJson); 

    $result = DB::transaction(function () use ($request, $trip, $replace, $promptJson, $plan) {

        $log = AiGeneration::create([
            'user_id' => $request->user()->id,
            'trip_id' => $trip->id,
            'prompt_json' => $promptJson,
            'result_json' => $plan,
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        ]);

        if ($replace) {
            $trip->load('days.items');
            foreach ($trip->days as $d) {
                $d->items()->delete();
            }
            $trip->days()->delete();
        }

        $createdDays = [];

        foreach (($plan['days'] ?? []) as $dayData) {
            $dayIndex = (int)($dayData['day'] ?? 1);

            $newDay = ItineraryDay::create([
                'trip_id' => $trip->id,
                'day_index' => $dayIndex,
                'date' => null,
                'title' => $dayData['title'] ?? ('Dan ' . $dayIndex),
            ]);

            $order = 1;
            foreach (($dayData['items'] ?? []) as $itemData) {
                ItineraryItem::create([
                    'itinerary_day_id' => $newDay->id,
                    'type' => $itemData['type'] ?? 'activity',
                    'title' => $itemData['title'] ?? 'Aktivnost',
                    'location' => $itemData['location'] ?? null,
                    'start_time' => $itemData['time'] ?? null, 
                    'end_time' => null,
                    'notes' => $itemData['notes'] ?? null,
                    'cost_estimate' => null,
                    'order' => $order++,
                ]);
            }

            $createdDays[] = $newDay;
        }

        return [
            'ai_generation_id' => $log->id,
        ];
    });

    return response()->json([
        'message' => 'AI plan primijenjen na putovanje.',
        'ai_generation_id' => $result['ai_generation_id'],
        'trip' => $trip->fresh()->load('days.items'),
    ], 201);
}
private function generatePlanFromOpenAI(array $promptJson): array
{
    $system = "Ti si travel planner. Vrati ISKLJUČIVO validan JSON bez dodatnog teksta.";
    $user = "Napravi plan putovanja kao JSON sa ovom strukturom:
{
  \"summary\": string,
  \"tips\": string[],
  \"days\": [
    {
      \"day\": number,
      \"title\": string,
      \"items\": [
        {\"type\":\"activity|food|transport|hotel\",\"time\":\"HH:MM\",\"title\":string,\"location\":string|null,\"notes\":string|null}
      ]
    }
  ]
}
Ulazni podaci: " . json_encode($promptJson, JSON_UNESCAPED_UNICODE);

    $apiKey = env('OPENAI_API_KEY');
    if (!$apiKey) {
        abort(500, 'OPENAI_API_KEY nije postavljen u .env');
    }

    $model = env('OPENAI_MODEL', 'gpt-4o-mini');

    $res = \Illuminate\Support\Facades\Http::withToken($apiKey)
        ->acceptJson()
        ->timeout(60)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => 0.7,
        ]);

    if (!$res->successful()) {
        abort(500, 'OpenAI error: ' . json_encode($res->json()));
    }

    $text = $res->json('choices.0.message.content');
    $text = trim((string)$text);

    $text = preg_replace('/^```json\s*/', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);

    $plan = json_decode($text, true);
    if (!is_array($plan)) {
        abort(422, 'AI nije vratio validan JSON.');
    }

    return $plan;
}

}
