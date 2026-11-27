<?php

use App\Domain\Accounts\Mask;
use App\Domain\Accounts\PasswordRules;
use App\Domain\Accounts\PhMobile;

it('normalizes Philippine mobile numbers', function (string $in, ?string $out) {
    expect(PhMobile::normalize($in))->toBe($out);
})->with([
    ['09171234567', '+639171234567'],
    ['9171234567', '+639171234567'],
    ['639171234567', '+639171234567'],
    ['+63 917 123 4567', '+639171234567'],
    ['0917-123-4567', '+639171234567'],
    ['(0917) 123.4567', '+639171234567'],
    ['0817 123 4567', null],
    ['0917123456', null],
    ['+1 917 123 4567', null],
    ['', null],
]);

it('formats a mobile as +63 9XX XXX XXXX', function () {
    expect(PhMobile::format('+639171234567'))->toBe('+63 917 123 4567');
});

it('masks contacts', function () {
    expect(Mask::email('jasmine.villanueva@tua.edu.ph'))->toBe('j****************a@tua.edu.ph');
    expect(Mask::email('ab@gmail.com'))->toBe('a*@gmail.com');
    expect(Mask::mobile('+639171234567'))->toBe('+63 9** *** 4567');
});

it('loads the common-password list', function () {
    expect(PasswordRules::commonList())->toContain('password1234')->not->toContain('# common passwords refused at registration and password change (claude.md §7 m1).');
});
