<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TourResource\Pages;
use App\Models\Tour;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Туры';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Основное')
                ->schema([
                    Forms\Components\Actions::make([
                        FormAction::make('generate')
                            ->label('Сгенерировать через LLM')
                            ->icon('heroicon-o-sparkles')
                            ->color('primary')
                            ->form([
                                Forms\Components\Textarea::make('prompt')
                                    ->label('Опишите тур одной-двумя фразами')
                                    ->placeholder('Например: 5-дневный поход по Камчатке с восхождением на вулкан')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->action(function (array $data, Set $set): void {
                                try {
                                    $generator = app(\App\Services\LLM\TourGenerator::class);
                                    $draft = $generator->generate($data['prompt']);
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Не удалось сгенерировать тур')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                $set('title', $draft['title']);
                                $set('short_description', $draft['short_description']);
                                $set('description', $draft['description']);
                                $set('duration_days', $draft['duration_days']);
                                $set('difficulty', $draft['difficulty']);
                                // Repeater::simple() takes a flat list of strings — no wrapping.
                                $set('highlights', $draft['highlights']);
                                $set('route_points', array_map(fn ($p) => [
                                    'lat' => (float) ($p['lat'] ?? 0),
                                    'lon' => (float) ($p['lon'] ?? 0),
                                    'label' => (string) ($p['label'] ?? ''),
                                ], $draft['route_points']));
                                if (! empty($draft['route_center'])) {
                                    $set('route_center.lat', (float) ($draft['route_center']['lat'] ?? 0));
                                    $set('route_center.lon', (float) ($draft['route_center']['lon'] ?? 0));
                                    $set('route_center.zoom', (int) ($draft['route_center']['zoom'] ?? 9));
                                }
                                $set('dates', array_map(fn ($d) => [
                                    'start_date' => $d['start_date'] ?? null,
                                    'end_date' => $d['end_date'] ?? null,
                                    'price' => (float) ($d['price'] ?? 0),
                                    'currency' => $d['currency'] ?? 'RUB',
                                    'seats_total' => (int) ($d['seats_total'] ?? 10),
                                    'seats_available' => (int) ($d['seats_total'] ?? 10),
                                ], $draft['dates']));

                                Notification::make()
                                    ->title('Черновик готов — проверьте поля и сохраните')
                                    ->success()
                                    ->send();
                            }),
                    ])->fullWidth()->columnSpanFull(),

                    Forms\Components\TextInput::make('title')
                        ->label('Название')
                        ->required()
                        ->maxLength(150)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) =>
                            empty($get('slug')) ? $set('slug', Str::slug($state)) : null
                        ),
                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->maxLength(180)
                        ->unique(ignoreRecord: true),
                    Forms\Components\Textarea::make('short_description')
                        ->label('Краткое описание')
                        ->required()
                        ->maxLength(500)
                        ->rows(2)
                        ->columnSpanFull(),
                    Forms\Components\RichEditor::make('description')
                        ->label('Полное описание')
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('duration_days')
                            ->label('Дней')->numeric()->required()->minValue(1)->default(1),
                        Forms\Components\TextInput::make('duration_hours')
                            ->label('Часов (если коротко)')->numeric()->minValue(0),
                        Forms\Components\Select::make('difficulty')
                            ->label('Сложность')
                            ->options([
                                'easy' => 'Лёгкий',
                                'moderate' => 'Средний',
                                'hard' => 'Сложный',
                            ])->required()->default('easy'),
                    ]),
                    Forms\Components\Toggle::make('is_published')
                        ->label('Опубликован')->default(true),
                    Forms\Components\Select::make('categories')
                        ->label('Категории')
                        ->multiple()
                        ->relationship('categories', 'name')
                        ->preload()
                        ->searchable()
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required(),
                            Forms\Components\TextInput::make('slug')->required(),
                            Forms\Components\TextInput::make('icon')->placeholder('heroicon-o-map'),
                        ]),
                    Forms\Components\Repeater::make('highlights')
                        ->label('Highlights')
                        ->simple(
                            Forms\Components\TextInput::make('value')->required()->maxLength(140)
                        )
                        ->columnSpanFull()
                        ->defaultItems(0),
                ])->columns(2),

            Forms\Components\Section::make('Фотоальбом')->schema([
                Forms\Components\FileUpload::make('cover_image')
                    ->label('Обложка')
                    ->image()
                    ->disk('public')
                    ->directory('covers')
                    ->imageEditor()
                    ->columnSpanFull(),
                Forms\Components\Repeater::make('photos')
                    ->relationship('photos')
                    ->label('Фото в альбоме')
                    ->orderColumn('sort_order')
                    ->reorderable()
                    ->schema([
                        Forms\Components\FileUpload::make('path')
                            ->label('Файл')
                            ->image()
                            ->disk('public')
                            ->directory('tours')
                            ->imageEditor()
                            ->required(),
                        Forms\Components\TextInput::make('alt')->label('Подпись')->maxLength(160),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]),

            Forms\Components\Section::make('Маршрут (Яндекс Карты)')->schema([
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('route_center.lat')
                        ->label('Центр карты · широта')->numeric()->step(0.000001),
                    Forms\Components\TextInput::make('route_center.lon')
                        ->label('Центр карты · долгота')->numeric()->step(0.000001),
                    Forms\Components\TextInput::make('route_center.zoom')
                        ->label('Zoom (7–14)')->numeric()->minValue(3)->maxValue(18)->default(9),
                ]),
                Forms\Components\Repeater::make('route_points')
                    ->label('Точки маршрута (по порядку)')
                    ->schema([
                        Forms\Components\TextInput::make('lat')
                            ->label('Широта')->numeric()->step(0.000001)->required(),
                        Forms\Components\TextInput::make('lon')
                            ->label('Долгота')->numeric()->step(0.000001)->required(),
                        Forms\Components\TextInput::make('label')->label('Подпись'),
                    ])
                    ->columns(3)
                    ->minItems(2)
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Даты и цены')->schema([
                Forms\Components\Repeater::make('dates')
                    ->relationship('dates')
                    ->label('Варианты дат')
                    ->schema([
                        Forms\Components\DatePicker::make('start_date')->label('Начало')->required(),
                        Forms\Components\DatePicker::make('end_date')->label('Окончание')->required(),
                        Forms\Components\TextInput::make('price')->label('Цена')->numeric()->required()->minValue(0),
                        Forms\Components\Select::make('currency')->options([
                            'RUB' => 'RUB', 'USD' => 'USD', 'EUR' => 'EUR',
                        ])->required()->default('RUB'),
                        Forms\Components\TextInput::make('seats_total')->label('Всего мест')
                            ->numeric()->required()->minValue(0)->default(10)->live()
                            ->afterStateUpdated(fn ($state, Set $set) => $set('seats_available', $state)),
                        Forms\Components\TextInput::make('seats_available')->label('Свободно')
                            ->numeric()->required()->minValue(0)->default(10),
                    ])
                    ->columns(3)
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')->label('Обложка')->disk('public'),
                Tables\Columns\TextColumn::make('title')->label('Название')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('categories.name')->label('Категории')->badge(),
                Tables\Columns\TextColumn::make('duration_days')->label('Дней')->sortable(),
                Tables\Columns\TextColumn::make('difficulty')->label('Сложность')->badge(),
                Tables\Columns\IconColumn::make('is_published')->label('Опубл.')->boolean(),
                Tables\Columns\TextColumn::make('dates_count')->counts('dates')->label('Дат'),
                Tables\Columns\TextColumn::make('updated_at')->label('Обновлен')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_published')->label('Опубликовано'),
                Tables\Filters\SelectFilter::make('difficulty')->options([
                    'easy' => 'Лёгкий', 'moderate' => 'Средний', 'hard' => 'Сложный',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('reindex')
                    ->label('Переиндексировать')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Tour $record) {
                        $indexer = app(\App\Services\Tours\TourIndexer::class);
                        $ok = $indexer->index($record);
                        Notification::make()
                            ->title($ok ? 'Эмбеддинг обновлён' : 'Сервис эмбеддингов недоступен')
                            ->{$ok ? 'success' : 'warning'}()
                            ->send();
                    }),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
