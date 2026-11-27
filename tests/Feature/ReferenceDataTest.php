<?php

use App\Models\AcademicUnit;
use App\Models\ResearchCluster;
use App\Models\Sdg;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Spatie\Permission\Models\Role;

// Sample records are tested once in SampleRecordsTest; they are slow to seed for every test.
beforeEach(fn () => $this->seed([ReferenceDataSeeder::class, RoleSeeder::class, SettingsSeeder::class, DemoUserSeeder::class]));

it('seeds the six colleges in their assumed clusters', function () {
    expect(AcademicUnit::count())->toBe(6);
    expect(AcademicUnit::where('code', 'CEIS')->first()->researchCluster->number)->toBe(1);
    expect(AcademicUnit::where('code', 'SLCN')->first()->researchCluster->number)->toBe(3);
    expect(ResearchCluster::count())->toBe(4);
});

it('seeds all 17 SDGs', function () {
    expect(Sdg::count())->toBe(17);
    expect(Sdg::find(17)->name)->toBe('Partnerships for the Goals');
});

it('seeds exactly the five roles', function () {
    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(collect(User::ROLES)->sort()->values()->all());
});

it('seeds one active demo user per role', function (string $role) {
    $users = User::role($role)->get();
    expect($users)->toHaveCount(1);
    expect($users->first()->canSignIn())->toBeTrue();
    expect(User::isTuaEmail($users->first()->email))->toBeTrue();
})->with(User::ROLES);

it('renders the design styleguide', function () {
    $this->get('/styleguide')->assertOk()->assertSee('TUA Research Repository')->assertSee('Probable duplicate');
});
