<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\LaptopModel;
use App\Models\PartCategory;
use Illuminate\Database\Seeder;

/**
 * Shop categories, spare-part item types, and a starter set of laptop
 * brands → models → generations. Safe to re-run (matches on slug / name).
 */
class CatalogStructureSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Laptops', 'laptop'],
            ['PC Components', 'cpu'],
            ['Storage', 'storage'],
            ['RAM', 'ram'],
            ['Accessories', 'headphones'],
            ['Chargers & Adapters', 'bolt'],
            ['Cables', 'tag'],
        ];
        foreach ($categories as $i => [$name, $icon]) {
            Category::updateOrCreate(['slug' => str($name)->slug()->value()], ['name' => $name, 'icon' => $icon, 'sort_order' => $i]);
        }

        $parts = [
            'LCD / LED Display', 'Keyboard', 'Battery', 'Speaker', 'Cooling Fan', 'Motherboard',
            'Hinge', 'Touchpad', 'DC Power Jack', 'Display Cable', 'Palmrest / Housing', 'Webcam',
        ];
        foreach ($parts as $i => $name) {
            PartCategory::updateOrCreate(['slug' => str($name)->slug()->value()], ['name' => $name, 'sort_order' => $i]);
        }

        // brand => [model => [[label, from, to], ...]]
        $models = [
            'Dell' => [
                'Inspiron 15 3520' => [['2022–2024', 2022, 2024]],
                'Inspiron 15 3511' => [['2020–2022', 2020, 2022]],
                'Latitude 5420' => [['2021–2023', 2021, 2023]],
                'Vostro 3510' => [['2021–2023', 2021, 2023]],
            ],
            'HP' => [
                'Pavilion 15' => [['2021–2023 (eg series)', 2021, 2023], ['2019–2020 (cs series)', 2019, 2020]],
                '250 G8' => [['2020–2022', 2020, 2022]],
                'EliteBook 840 G7' => [['2020–2021', 2020, 2021]],
            ],
            'Lenovo' => [
                'IdeaPad 3 15' => [['2021–2023 (Gen 6)', 2021, 2023], ['2019–2020 (Gen 5)', 2019, 2020]],
                'ThinkPad E14' => [['2020–2022', 2020, 2022]],
                'ThinkPad T480' => [['2018–2019', 2018, 2019]],
            ],
            'ASUS' => [
                'VivoBook 15 X515' => [['2021–2023', 2021, 2023]],
                'TUF Gaming F15' => [['2021–2023', 2021, 2023]],
            ],
            'Acer' => [
                'Aspire 5 A515' => [['2021–2023', 2021, 2023]],
                'Aspire 3 A315' => [['2020–2022', 2020, 2022]],
            ],
            'MSI' => ['GF63 Thin' => [['2021–2023', 2021, 2023]]],
            'Apple' => [
                'MacBook Air 13" M1 (A2337)' => [['2020–2022', 2020, 2022]],
                'MacBook Pro 13" (A2338)' => [['2020–2022', 2020, 2022]],
            ],
            'Toshiba' => ['Satellite C50' => [['2015–2017', 2015, 2017]]],
            'Samsung' => ['Galaxy Book' => [['2021–2023', 2021, 2023]]],
        ];

        $order = 0;
        foreach ($models as $brandName => $brandModels) {
            $brand = Brand::updateOrCreate(['slug' => str($brandName)->slug()->value()], ['name' => $brandName, 'sort_order' => $order++]);
            foreach ($brandModels as $modelName => $generations) {
                $model = LaptopModel::firstOrCreate(['brand_id' => $brand->id, 'name' => $modelName]);
                foreach ($generations as [$label, $from, $to]) {
                    $model->generations()->firstOrCreate(['label' => $label], ['year_from' => $from, 'year_to' => $to]);
                }
            }
        }
    }
}
