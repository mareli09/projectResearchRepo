<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchCluster;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One demo user per role, matching the prototype's sample people.
 * Local and evaluation copies only. Never run in production.
 * Password for all: Password123 (change it after the first sign-in).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoUserSeeder skipped in production.');

            return;
        }

        $ceis = AcademicUnit::where('code', 'CEIS')->firstOrFail();
        $bsit = Program::where('name', 'BS Information Technology')->firstOrFail();

        $people = [
            [User::ROLE_STUDENT, 'Jasmine Villanueva', 'jasmine.villanueva', 'student', $bsit->id],
            [User::ROLE_ADVISER, 'Prof. Ramon Castillo', 'ramon.castillo', 'faculty', null],
            [User::ROLE_COORDINATOR, 'Dr. Liza Ferrer', 'liza.ferrer', 'faculty', null],
            [User::ROLE_URDC, 'Dr. Teresa Manalo', 'urdc.admin', 'staff', null],
            [User::ROLE_SYSADMIN, 'Engr. Paolo Reyes', 'paolo.reyes', 'staff', null],
        ];

        foreach ($people as [$role, $name, $local, $type, $programId]) {
            $user = User::firstOrNew(['email' => $local.'@'.config('tua.email_domain')]);
            $user->fill([
                'name' => $name,
                'password' => 'Password123',
                'user_type' => $type,
                'academic_unit_id' => $role === User::ROLE_URDC || $role === User::ROLE_SYSADMIN ? null : $ceis->id,
                'program_id' => $programId,
            ]);
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();
            $user->syncRoles([$role]);
        }

        // The demo coordinator leads Cluster 1 (Business and Technology), as in the prototype.
        ResearchCluster::where('number', 1)->update([
            'coordinator_user_id' => User::where('email', 'liza.ferrer@'.config('tua.email_domain'))->value('id'),
        ]);
    }
}
