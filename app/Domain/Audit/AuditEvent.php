<?php

namespace App\Domain\Audit;

/** Activity log event types (CLAUDE.md §13). */
final class AuditEvent
{
    public const LOGIN = 'login';

    public const LOGOUT = 'logout';

    public const LOGIN_FAILED = 'login_failed';

    public const REGISTER = 'register';

    public const ACTIVATE = 'activate';

    public const RECOVERY = 'recovery';

    public const PASSWORD_RESET = 'password_reset';

    public const SEARCH = 'search';

    public const VIEW = 'view';

    public const DOWNLOAD = 'download';

    public const UPLOAD = 'upload';

    public const SUBMIT = 'submit';

    public const ENDORSED = 'endorsed';

    public const REVIEWED = 'reviewed';

    public const PUBLISHED = 'published';

    public const RETURNED = 'returned';

    public const REJECTED = 'rejected';

    public const REQUEST = 'request';

    public const LLM = 'llm';

    public const SERVICE = 'service';

    public const NOTIFY = 'notify';

    public const ROLE = 'role';

    public const SETTING = 'setting';

    public const CMS = 'cms';

    public const THEME = 'theme';

    public const IMPORT = 'import';

    public const BACKUP = 'backup';

    public const CLUSTER_RUN = 'cluster_run';

    public const METADATA_EDIT = 'metadata_edit';

    /** Events a user sees under "Recent security events" on their own account page. */
    public const SECURITY = [
        self::LOGIN, self::LOGOUT, self::LOGIN_FAILED, self::REGISTER, self::ACTIVATE,
        self::RECOVERY, self::PASSWORD_RESET, self::ROLE,
    ];

    /** @return list<string> */
    public static function all(): array
    {
        $constants = (new \ReflectionClass(self::class))->getConstants();

        return array_values(array_filter($constants, 'is_string'));
    }
}
