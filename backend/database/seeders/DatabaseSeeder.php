<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourPhoto;
use App\Models\User;
use App\Services\Tours\TourIndexer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedCategories();
        $this->seedTours();
        $this->indexAllTours();
    }

    private function seedAdmin(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@tours.local')],
            [
                'name' => 'Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]
        );
    }

    private function seedCategories(): void
    {
        // Slugs are explicit (instead of Str::slug on Russian names) so the
        // seed doesn't depend on the `intl` extension being present.
        $categories = [
            ['slug' => 'gory',            'name' => 'Горы',            'icon' => 'heroicon-o-cube'],
            ['slug' => 'more',            'name' => 'Море',            'icon' => 'heroicon-o-globe-europe-africa'],
            ['slug' => 'gastronomija',    'name' => 'Гастрономия',     'icon' => 'heroicon-o-cake'],
            ['slug' => 'goroda',          'name' => 'Города',          'icon' => 'heroicon-o-building-office-2'],
            ['slug' => 'aktivnyj-otdyh',  'name' => 'Активный отдых',  'icon' => 'heroicon-o-bolt'],
            ['slug' => 'semejnye',        'name' => 'Семейные',        'icon' => 'heroicon-o-user-group'],
            ['slug' => 'priroda',         'name' => 'Природа',         'icon' => 'heroicon-o-sun'],
            ['slug' => 'kultura',         'name' => 'Культура',        'icon' => 'heroicon-o-book-open'],
        ];
        foreach ($categories as $i => $row) {
            Category::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'icon' => $row['icon'],
                    'sort_order' => $i,
                ]
            );
        }
    }

    private function seedTours(): void
    {
        $tours = $this->sampleTours();

        foreach ($tours as $sample) {
            $tour = Tour::query()->updateOrCreate(
                ['slug' => $sample['slug']],
                [
                    'title' => $sample['title'],
                    'short_description' => $sample['short_description'],
                    'description' => $sample['description'],
                    'duration_days' => $sample['duration_days'],
                    'difficulty' => $sample['difficulty'],
                    'cover_image' => $sample['cover_image'],
                    'route_points' => $sample['route_points'],
                    'route_center' => $sample['route_center'],
                    'highlights' => $sample['highlights'],
                    'is_published' => true,
                ]
            );

            $tour->categories()->sync(
                Category::query()->whereIn('slug', $sample['category_slugs'])->pluck('id')->all()
            );

            $tour->photos()->delete();
            foreach ($sample['photos'] as $i => $photo) {
                TourPhoto::create([
                    'tour_id' => $tour->id,
                    'path' => $photo['url'],
                    'alt' => $photo['alt'],
                    'sort_order' => $i,
                ]);
            }

            $tour->dates()->delete();
            foreach ($sample['date_offsets'] as $offsetDays => $price) {
                $start = Carbon::today()->addDays($offsetDays);
                TourDate::create([
                    'tour_id' => $tour->id,
                    'start_date' => $start,
                    'end_date' => $start->copy()->addDays($sample['duration_days'] - 1),
                    'price' => $price,
                    'currency' => 'RUB',
                    'seats_total' => 12,
                    'seats_available' => random_int(2, 12),
                ]);
            }
        }
    }

    private function indexAllTours(): void
    {
        try {
            $indexer = app(TourIndexer::class);
            Tour::query()->with('categories')->chunk(50, function ($tours) use ($indexer) {
                foreach ($tours as $tour) {
                    $indexer->index($tour);
                }
            });
        } catch (\Throwable $e) {
            $this->command?->warn('Skipping embedding indexing: '.$e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function sampleTours(): array
    {
        $picsum = fn (int $seed) => "https://picsum.photos/seed/tour-$seed/1200/800";

        return [
            [
                'slug' => 'kamchatka-volcanoes',
                'title' => 'Камчатка: вулканы и горячие источники',
                'short_description' => 'Недельное путешествие к Авачинскому вулкану и термальным источникам Паратунки.',
                'description' => "За семь дней вы пройдёте по чёрному вулканическому песку, увидите парящие фумаролы Мутновского, искупаетесь в природных горячих ваннах Паратунки и встретите рассвет над Тихим океаном. Программа включает восхождение средней сложности, переезды на вездеходах и ужины с местной рыбой и крабом.\n\nГруппы небольшие — до 12 человек, в команде двое гидов и сопровождающий по безопасности. Мы продумали маршрут так, чтобы каждый день был насыщенным, но оставалось время полюбоваться видами и сделать кадры, которых нет в Instagram.",
                'duration_days' => 7,
                'difficulty' => 'moderate',
                'cover_image' => $picsum(11),
                'photos' => [
                    ['url' => $picsum(11), 'alt' => 'Авачинский вулкан на рассвете'],
                    ['url' => $picsum(12), 'alt' => 'Фумаролы Мутновского'],
                    ['url' => $picsum(13), 'alt' => 'Горячие источники Паратунки'],
                    ['url' => $picsum(14), 'alt' => 'Тихоокеанское побережье'],
                ],
                'highlights' => [
                    'Восхождение на Авачинский вулкан',
                    'Купание в природных горячих источниках',
                    'Морская прогулка к птичьим базарам',
                    'Камчатская кухня и краб',
                ],
                'category_slugs' => ['gory', 'priroda', 'aktivnyj-otdyh'],
                'route_points' => [
                    ['lat' => 53.0167, 'lon' => 158.6500, 'label' => 'Петропавловск-Камчатский'],
                    ['lat' => 53.2557, 'lon' => 158.8331, 'label' => 'База Авачинский перевал'],
                    ['lat' => 53.2628, 'lon' => 158.8311, 'label' => 'Авачинский вулкан'],
                    ['lat' => 52.5167, 'lon' => 158.1956, 'label' => 'Мутновский'],
                    ['lat' => 52.8330, 'lon' => 158.2500, 'label' => 'Паратунка'],
                ],
                'route_center' => ['lat' => 52.9, 'lon' => 158.5, 'zoom' => 8],
                'date_offsets' => [21 => 138000, 60 => 142000, 95 => 145000],
            ],
            [
                'slug' => 'altai-katun-rafting',
                'title' => 'Алтай: сплав по Катуни и таёжные тропы',
                'short_description' => 'Пять дней рафтинга по бирюзовой Катуни с ночёвками в кедровом лесу.',
                'description' => "Маршрут стартует в Чемале и проходит по самым живописным порогам средней Катуни. Утром — гребля и бирюзовая вода, днём — горячий обед на берегу и купание в Аржан-Суу, вечером — баня на дровах и звёздное небо над Чуйским трактом.\n\nПодходит для начинающих: пороги 2-3 категории, страховка и тёплое снаряжение в комплекте. Можно с детьми от 12 лет.",
                'duration_days' => 5,
                'difficulty' => 'moderate',
                'cover_image' => $picsum(21),
                'photos' => [
                    ['url' => $picsum(21), 'alt' => 'Сплав по Катуни'],
                    ['url' => $picsum(22), 'alt' => 'Лагерь в кедровом лесу'],
                    ['url' => $picsum(23), 'alt' => 'Чемал'],
                ],
                'highlights' => [
                    'Сплав по порогам 2-3 категории',
                    'Баня на дровах у реки',
                    'Источник Аржан-Суу',
                    'Дегустация алтайского мёда',
                ],
                'category_slugs' => ['gory', 'aktivnyj-otdyh', 'priroda'],
                'route_points' => [
                    ['lat' => 51.4078, 'lon' => 85.9931, 'label' => 'Чемал'],
                    ['lat' => 51.7000, 'lon' => 86.7000, 'label' => 'Усть-Сема'],
                    ['lat' => 51.7500, 'lon' => 86.9000, 'label' => 'Чуйский тракт'],
                ],
                'route_center' => ['lat' => 51.6, 'lon' => 86.4, 'zoom' => 9],
                'date_offsets' => [14 => 56000, 45 => 58000, 80 => 61000],
            ],
            [
                'slug' => 'kazan-gastro-weekend',
                'title' => 'Казань: гастрономические выходные',
                'short_description' => 'Три дня, десять ресторанов и личные мастер-классы от шефов.',
                'description' => "Маршрут собрал лучшие места столицы Татарстана: от исторической Старо-Татарской слободы до новых проектов на Кремлёвской набережной. Вы попробуете эчпочмаки, чак-чак, азу и современную авторскую кухню — и сравните, как татарские традиции переосмысливают молодые повара.\n\nКаждый день — два ресторана, прогулка с гидом-историком и небольшой мастер-класс: лепка эчпочмаков, варка чак-чака, дегустация чая по-татарски.",
                'duration_days' => 3,
                'difficulty' => 'easy',
                'cover_image' => $picsum(31),
                'photos' => [
                    ['url' => $picsum(31), 'alt' => 'Казанский кремль вечером'],
                    ['url' => $picsum(32), 'alt' => 'Татарская кухня'],
                    ['url' => $picsum(33), 'alt' => 'Кремлёвская набережная'],
                ],
                'highlights' => [
                    '10 ресторанов и кафе разных форматов',
                    'Мастер-класс по эчпочмакам',
                    'Дегустация чая по-татарски',
                    'Прогулка по Старо-Татарской слободе',
                ],
                'category_slugs' => ['gastronomija', 'goroda', 'kultura'],
                'route_points' => [
                    ['lat' => 55.7980, 'lon' => 49.1064, 'label' => 'Казанский Кремль'],
                    ['lat' => 55.7820, 'lon' => 49.1180, 'label' => 'Старо-Татарская слобода'],
                    ['lat' => 55.7913, 'lon' => 49.1066, 'label' => 'Кремлёвская набережная'],
                ],
                'route_center' => ['lat' => 55.79, 'lon' => 49.11, 'zoom' => 13],
                'date_offsets' => [7 => 32000, 30 => 34000, 60 => 36000],
            ],
            [
                'slug' => 'baikal-winter-ice',
                'title' => 'Зимний Байкал: лёд, хивус и пещеры',
                'short_description' => 'Четыре дня по прозрачному льду с переходом на остров Ольхон.',
                'description' => "В феврале Байкал становится огромным катком — миллионы трещин, голубые торосы и сюрреалистические гроты. Мы перемещаемся на хивусе (судно на воздушной подушке), останавливаемся в лучших точках для фото и заходим в ледяные пещеры.\n\nНочёвки на острове Ольхон, баня после прогулки, омуль горячего копчения. Обязательное снаряжение выдаётся: шипы, тулупы, очки.",
                'duration_days' => 4,
                'difficulty' => 'easy',
                'cover_image' => $picsum(41),
                'photos' => [
                    ['url' => $picsum(41), 'alt' => 'Лёд Байкала'],
                    ['url' => $picsum(42), 'alt' => 'Остров Ольхон'],
                    ['url' => $picsum(43), 'alt' => 'Ледяные гроты'],
                ],
                'highlights' => [
                    'Прозрачный лёд и торосы',
                    'Передвижение на хивусе',
                    'Ледяные гроты Ольхона',
                    'Байкальский омуль и баня',
                ],
                'category_slugs' => ['priroda', 'aktivnyj-otdyh', 'semejnye'],
                'route_points' => [
                    ['lat' => 52.2956, 'lon' => 104.2966, 'label' => 'Иркутск'],
                    ['lat' => 53.2030, 'lon' => 107.3450, 'label' => 'Хужир, о. Ольхон'],
                    ['lat' => 53.0640, 'lon' => 107.3580, 'label' => 'Мыс Хобой'],
                ],
                'route_center' => ['lat' => 52.9, 'lon' => 106.5, 'zoom' => 7],
                'date_offsets' => [40 => 72000, 65 => 75000, 110 => 78000],
            ],
            [
                'slug' => 'krasnaya-polyana-snow',
                'title' => 'Красная Поляна: лыжи и термы',
                'short_description' => 'Шесть дней катания по трём курортам с восстановлением в термальных бассейнах.',
                'description' => "Базируемся в Красной Поляне, утром катаемся в Розе Хутор, Газпроме или Горки Городе по ски-пассу всех трёх курортов, вечером — термальные комплексы и ужины в авторских ресторанах. Подходит как начинающим, так и продвинутым: 18 км трасс синих, 50 км красных, ночные катания.\n\nПо запросу — индивидуальный инструктор, фрирайд с гидом, фотограф на трассе.",
                'duration_days' => 6,
                'difficulty' => 'moderate',
                'cover_image' => $picsum(51),
                'photos' => [
                    ['url' => $picsum(51), 'alt' => 'Роза Хутор'],
                    ['url' => $picsum(52), 'alt' => 'Подъёмник'],
                    ['url' => $picsum(53), 'alt' => 'Термы'],
                ],
                'highlights' => [
                    'Единый ски-пасс на 3 курорта',
                    'Термальные комплексы',
                    'Авторские рестораны Красной Поляны',
                    'Опциональный фрирайд с гидом',
                ],
                'category_slugs' => ['gory', 'aktivnyj-otdyh'],
                'route_points' => [
                    ['lat' => 43.6716, 'lon' => 40.2536, 'label' => 'Роза Хутор'],
                    ['lat' => 43.6826, 'lon' => 40.2885, 'label' => 'Газпром'],
                    ['lat' => 43.6890, 'lon' => 40.3050, 'label' => 'Горки Город'],
                ],
                'route_center' => ['lat' => 43.68, 'lon' => 40.28, 'zoom' => 12],
                'date_offsets' => [10 => 89000, 40 => 92000, 80 => 96000],
            ],
            [
                'slug' => 'crimea-coast-bike',
                'title' => 'Велотур по Южному берегу Крыма',
                'short_description' => 'Семь дней на велосипеде вдоль скал и виноградников Ялты и Судака.',
                'description' => "Маршрут идёт по старой Таврической дороге и тропе Голицына: меняются виды, ландшафты и сорта вин. Среднедневная дистанция 30-40 км, есть сложные подъёмы — поэтому групповая поддержка с микроавтобусом.\n\nВ программе — Никитский ботанический сад, винодельня в Массандре, купание на пляжах Гурзуфа и пешеходная экскурсия по Судакской крепости.",
                'duration_days' => 7,
                'difficulty' => 'hard',
                'cover_image' => $picsum(61),
                'photos' => [
                    ['url' => $picsum(61), 'alt' => 'Ласточкино гнездо'],
                    ['url' => $picsum(62), 'alt' => 'Судакская крепость'],
                    ['url' => $picsum(63), 'alt' => 'Велогруппа на серпантине'],
                ],
                'highlights' => [
                    'Тропа Голицына',
                    'Винодельня Массандра',
                    'Никитский ботанический сад',
                    'Купание на пляжах ЮБК',
                ],
                'category_slugs' => ['more', 'aktivnyj-otdyh', 'kultura'],
                'route_points' => [
                    ['lat' => 44.4910, 'lon' => 34.1631, 'label' => 'Ялта'],
                    ['lat' => 44.5093, 'lon' => 34.2864, 'label' => 'Гурзуф'],
                    ['lat' => 44.8493, 'lon' => 34.9596, 'label' => 'Судак'],
                ],
                'route_center' => ['lat' => 44.65, 'lon' => 34.5, 'zoom' => 9],
                'date_offsets' => [25 => 64000, 55 => 67000, 90 => 70000],
            ],
        ];
    }
}
