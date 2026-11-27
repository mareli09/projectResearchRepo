<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Program;
use App\Models\ResearchCluster;
use App\Models\Sdg;
use Illuminate\Database\Seeder;

/**
 * Colleges, programs, URDC clusters and SDGs (CLAUDE.md §5).
 * Cluster membership and programs are assumptions until the URDC confirms them (§20 item 1).
 */
class ReferenceDataSeeder extends Seeder
{
    public const CLUSTERS = [
        1 => 'Business and Technology',
        2 => 'Health: Diagnostic and Pharmaceutical',
        3 => 'Health: Patient Care',
        4 => 'Social Science, Humanities, and Science',
    ];

    /** code => [name, cluster number, [program => degree level]] */
    public const COLLEGES = [
        'CAHS' => ['College of Allied Health Sciences', 2, [
            'BS Physical Therapy' => 'undergraduate', 'BS Pharmacy' => 'undergraduate', 'BS Radiologic Technology' => 'undergraduate',
        ]],
        'CASE' => ['College of Arts, Sciences and Education', 4, [
            'BS Psychology' => 'undergraduate', 'BSEd English' => 'undergraduate', 'AB Communication' => 'undergraduate', 'BS Biology' => 'undergraduate',
        ]],
        'IBAM' => ['Institute of Business and Management', 1, [
            'BSBA Marketing' => 'undergraduate', 'BS Accountancy' => 'undergraduate', 'BS Hospitality Management' => 'undergraduate',
        ]],
        'CEIS' => ['College of Engineering and Information Sciences', 1, [
            'BS Information Technology' => 'undergraduate', 'BS Computer Science' => 'undergraduate',
            'BS Computer Engineering' => 'undergraduate', 'BS Civil Engineering' => 'undergraduate',
        ]],
        'CMT' => ['College of Medical Technology', 2, [
            'BS Medical Technology' => 'undergraduate',
        ]],
        'SLCN' => ["St. Luke's College of Nursing", 3, [
            'BS Nursing' => 'undergraduate', 'Master of Arts in Nursing' => 'graduate',
        ]],
    ];

    /** All 17 UN Sustainable Development Goals. */
    public const SDGS = [
        1 => 'No Poverty', 2 => 'Zero Hunger', 3 => 'Good Health and Well-being', 4 => 'Quality Education',
        5 => 'Gender Equality', 6 => 'Clean Water and Sanitation', 7 => 'Affordable and Clean Energy',
        8 => 'Decent Work and Economic Growth', 9 => 'Industry, Innovation and Infrastructure',
        10 => 'Reduced Inequalities', 11 => 'Sustainable Cities and Communities',
        12 => 'Responsible Consumption and Production', 13 => 'Climate Action', 14 => 'Life Below Water',
        15 => 'Life on Land', 16 => 'Peace, Justice and Strong Institutions', 17 => 'Partnerships for the Goals',
    ];

    public function run(): void
    {
        $clusters = [];
        foreach (self::CLUSTERS as $number => $name) {
            $clusters[$number] = ResearchCluster::updateOrCreate(['number' => $number], ['name' => $name]);
        }

        foreach (self::COLLEGES as $code => [$name, $cluster, $programs]) {
            $unit = AcademicUnit::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'research_cluster_id' => $clusters[$cluster]->id],
            );
            foreach ($programs as $program => $level) {
                Program::updateOrCreate(
                    ['academic_unit_id' => $unit->id, 'name' => $program],
                    ['degree_level' => $level],
                );
            }
        }

        foreach (self::SDGS as $id => $name) {
            Sdg::updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
