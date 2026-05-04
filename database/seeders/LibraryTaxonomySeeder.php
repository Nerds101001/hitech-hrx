<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LibraryTaxonomy;

class LibraryTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'RUST-X', 'desc' => 'VCI & corrosion protection', 'color' => '#f44336', 'categories' => ['VCI Packaging', 'VCI Emitters', 'VCI Sprays', 'Rust Preventive Oils', 'Cleaners', 'Coatings']],
            ['name' => 'Dr.Bio', 'desc' => 'Biodegradable solutions', 'color' => '#4caf50', 'categories' => ['Bio-Degradable Cleaners', 'Eco-Friendly Oils', 'Sustainable Packaging']],
            ['name' => 'Fillezy', 'desc' => 'Void fill systems', 'color' => '#ff9800', 'categories' => ['Void Fill Systems', 'Air Cushions', 'Paper Packaging']],
            ['name' => 'KIF', 'desc' => 'Fresh produce packaging', 'color' => '#2196f3', 'categories' => ['Freshness Preservation', 'Ethylene Control', 'Cold Chain']],
            ['name' => 'ZOrbit', 'desc' => 'Desiccants & absorbents', 'color' => '#ff5722', 'categories' => ['Desiccants', 'Silica Gel', 'Container Desiccants']],
            ['name' => 'Tuffpaulin', 'desc' => 'Heavy-duty covers', 'color' => '#673ab7', 'categories' => ['Heavy Duty Tarps', 'Industrial Covers', 'Pond Liners']],
            ['name' => 'HITECH', 'desc' => 'Corporate & General', 'color' => '#607d8b', 'categories' => ['Industrial Assets', 'Corporate Docs', 'Training Material']]
        ];

        foreach ($brands as $b) {
            $brand = LibraryTaxonomy::create([
                'type' => 'brand',
                'name' => $b['name'],
                'description' => $b['desc'],
                'color' => $b['color']
            ]);

            foreach ($b['categories'] as $cat) {
                LibraryTaxonomy::create([
                    'type' => 'category',
                    'name' => $cat,
                    'parent_id' => $brand->id
                ]);
            }
        }
    }
}
