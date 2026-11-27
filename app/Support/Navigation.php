<?php

namespace App\Support;

use App\Models\AccessRequest;
use App\Models\ResearchCluster;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/** Left navigation grouped by role (CLAUDE.md §15), matching the prototype's menus. */
final class Navigation
{
    public const ROLE_KEYS = [
        User::ROLE_STUDENT => 'student',
        User::ROLE_ADVISER => 'adviser',
        User::ROLE_COORDINATOR => 'coordinator',
        User::ROLE_URDC => 'urdc',
        User::ROLE_SYSADMIN => 'sysadmin',
    ];

    /** Home page of each role after sign-in. */
    public const HOME = [
        'student' => 'student.home',
        'adviser' => 'adviser.queue',
        'coordinator' => 'coordinator.queue',
        'urdc' => 'urdc.analytics',
        'sysadmin' => 'admin.health',
    ];

    /** Sidebar icon per route (resources/views/components/icon.blade.php). */
    public const ICONS = [
        'student.home' => 'home', 'student.submit' => 'upload', 'student.status' => 'status', 'student.summary' => 'pie',
        'adviser.queue' => 'inbox', 'adviser.review' => 'review', 'adviser.supervised' => 'users',
        'coordinator.queue' => 'inbox', 'coordinator.screening' => 'compare', 'coordinator.themes' => 'tag', 'coordinator.analytics' => 'chart',
        'urdc.analytics' => 'chart', 'urdc.approvals' => 'check', 'urdc.requests' => 'key', 'urdc.records' => 'folder', 'urdc.import' => 'import',
        'urdc.clustering' => 'cluster', 'urdc.ai' => 'ai', 'activity' => 'log', 'admin.users' => 'users', 'site.content' => 'globe',
        'admin.health' => 'heart', 'admin.services' => 'shield', 'admin.settings' => 'settings', 'admin.backups' => 'database', 'admin.evaluators' => 'clock',
        'notifications' => 'bell', 'search' => 'search', 'account' => 'user',
    ];

    public static function roleKey(?User $user): ?string
    {
        $role = $user?->getRoleNames()->first();

        return $role ? (self::ROLE_KEYS[$role] ?? null) : null;
    }

    /** @return array<string, list<array{0: string, 1: string, 2: int|string|null}>> group => [label, route, badge] */
    public static function for(User $user): array
    {
        $key = self::roleKey($user);
        $unreadCount = self::unread($user) ?: null;
        $library = ['Library' => [['Search', 'search', null]]];
        $account = ['Account' => [['Account and recovery', 'account', null]]];
        $clusterId = $key === 'coordinator' ? ResearchCluster::where('coordinator_user_id', $user->id)->value('id') : null;

        $groups = match ($key) {
            'student' => ['My research' => [
                ['Home', 'student.home', null], ['Submit a study', 'student.submit', null],
                ['Submission status', 'student.status', Submission::where('student_user_id', $user->id)->where('status', 'returned')->count() ?: null],
                ['Notifications', 'notifications', $unreadCount],
            ]] + ['Library' => [['Search', 'search', null], ['Research summary', 'student.summary', null]]] + $account,
            'adviser' => ['Advising' => [
                ['Endorsement queue', 'adviser.queue', Submission::where('adviser_user_id', $user->id)->where('status', 'submitted')->count() ?: null],
                ['Review submission', 'adviser.review', null], ['Supervised studies', 'adviser.supervised', null],
                ['Notifications', 'notifications', $unreadCount],
            ]] + $library + $account,
            'coordinator' => ['Cluster work' => [
                ['Review queue', 'coordinator.queue', $clusterId ? (Submission::where('research_cluster_id', $clusterId)->where('status', 'endorsed')->count() ?: null) : null],
                ['Similarity review', 'coordinator.screening', null], ['Theme labels', 'coordinator.themes', 2],
                ['Cluster analytics', 'coordinator.analytics', null], ['Notifications', 'notifications', $unreadCount],
            ]] + $library + $account,
            'urdc' => [
                'Overview' => [
                    ['Analytics dashboard', 'urdc.analytics', null],
                    ['Approve and publish', 'urdc.approvals', Submission::where('status', 'reviewed')->count() ?: null],
                    ['Full-paper requests', 'urdc.requests', AccessRequest::where('status', 'pending')->count() ?: null],
                    ['Notifications', 'notifications', $unreadCount],
                ],
                'Repository' => [
                    ['Records and metadata', 'urdc.records', null], ['Bulk import', 'urdc.import', null],
                    ['Theme clustering', 'urdc.clustering', null], ['AI performance', 'urdc.ai', null],
                ],
                'Monitoring' => [['Activity log', 'activity', null], ['Account help', 'admin.users', null]],
                'Website' => [['Landing page content', 'site.content', null]],
            ] + $library + $account,
            'sysadmin' => [
                'Administration' => [
                    ['System health', 'admin.health', null], ['Services and security', 'admin.services', null],
                    ['Users and roles', 'admin.users', null], ['System settings', 'admin.settings', null],
                    ['Backups', 'admin.backups', null], ['Evaluator accounts', 'admin.evaluators', null],
                    ['Activity log', 'activity', null],
                ],
                'Website' => [['Landing page content', 'site.content', null]],
            ] + $account,
            default => $account,
        };

        // Hide switched-off features and routes that do not exist.
        foreach ($groups as $g => $items) {
            $groups[$g] = array_values(array_filter($items, fn ($i) => Route::has($i[1])
                && ! ($i[1] === 'site.content' && ! config('tua.features.landing_cms'))
                && ! ($i[1] === 'admin.evaluators' && ! config('tua.evaluation_copy') && ! app()->isLocal() && ! app()->runningUnitTests())));
        }

        return array_filter($groups);
    }

    /** Unread in-app notifications of a user (the bell in the top bar). */
    public static function unread(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
