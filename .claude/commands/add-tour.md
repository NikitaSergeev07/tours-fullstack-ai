---
description: Generate and persist a new tour from a free-form description, reusing the same LLM path as the admin "Сгенерировать через LLM" button.
argument-hint: "<one-line prompt describing the tour>"
allowed-tools: Bash
---

Generate a tour draft from the prompt `$ARGUMENTS`, then save it
end-to-end (model + photos + dates + embedding). Use the same code path
as the admin — that way any LLM prompt drift is caught in CI.

Steps:

1. Confirm services are up: `docker compose ps backend embeddings postgres`.
2. Generate via tinker:
   ```bash
   docker compose exec -T backend php artisan tinker --execute='
     $prompt = $argv[0];
     $gen = app(\App\Services\LLM\TourGenerator::class);
     $draft = $gen->generate($prompt);
     $tour = \App\Models\Tour::create([
       "title" => $draft["title"],
       "short_description" => $draft["short_description"],
       "description" => $draft["description"],
       "duration_days" => $draft["duration_days"],
       "difficulty" => $draft["difficulty"],
       "highlights" => $draft["highlights"],
       "route_points" => $draft["route_points"],
       "route_center" => $draft["route_center"],
       "cover_image" => "https://picsum.photos/seed/" . random_int(1, 9999) . "/1200/800",
       "is_published" => true,
     ]);
     $cats = collect($draft["categories"])
       ->map(fn($n) => \App\Models\Category::firstOrCreate(["slug" => \Str::slug($n)], ["name" => $n]))
       ->pluck("id");
     $tour->categories()->sync($cats);
     foreach ($draft["dates"] as $d) { $tour->dates()->create($d + ["seats_available" => $d["seats_total"] ?? 10]); }
     app(\App\Services\Tours\TourIndexer::class)->index($tour);
     dump($tour->slug);
   ' -- "$ARGUMENTS"
   ```
3. Verify the tour shows up:
   ```bash
   curl -fsS "http://localhost:8000/api/tours?q=$(printf %s "$ARGUMENTS" | jq -sRr @uri)" | jq '.data[0] | {slug,title,score}'
   ```
4. Print the slug and the public URL: `http://localhost:3000/tour/<slug>`.

Stop and report if `ANTHROPIC_API_KEY` is empty — the LLM call will
otherwise return a generic error.
