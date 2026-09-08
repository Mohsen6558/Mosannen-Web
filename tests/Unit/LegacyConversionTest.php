<?php

use App\Support\JalaliDate;
use App\Support\Permissions;
use App\Support\Teeth;

describe('tooth notation', function () {
    it('has a complete FDI catalogue', function () {
        expect(Teeth::permanent())->toHaveCount(32)
            ->and(Teeth::primary())->toHaveCount(20)
            ->and(Teeth::all())->toHaveCount(52);
    });

    it('converts permanent legacy tokens to FDI codes', function () {
        // Quadrants: UR=1x UL=2x LL=3x LR=4x
        expect(Teeth::fromLegacyToken('_1UR'))->toBe('11')
            ->and(Teeth::fromLegacyToken('_3UR'))->toBe('13')
            ->and(Teeth::fromLegacyToken('_8UL'))->toBe('28')
            ->and(Teeth::fromLegacyToken('_6LL'))->toBe('36')
            ->and(Teeth::fromLegacyToken('_7LR'))->toBe('47');
    });

    it('converts primary legacy tokens to FDI codes', function () {
        // Primary letters A-E map to 1-5; quadrants UR=5x UL=6x LL=7x LR=8x
        expect(Teeth::fromLegacyToken('_AUR'))->toBe('51')
            ->and(Teeth::fromLegacyToken('_EUL'))->toBe('65')
            ->and(Teeth::fromLegacyToken('_CLL'))->toBe('73')
            ->and(Teeth::fromLegacyToken('_ELR'))->toBe('85');
    });

    it('rejects tokens that are not teeth', function () {
        expect(Teeth::fromLegacyToken('_bitewing'))->toBeNull()
            ->and(Teeth::fromLegacyToken('_9UR'))->toBeNull()
            ->and(Teeth::fromLegacyToken('_FUL'))->toBeNull()
            ->and(Teeth::fromLegacyToken(''))->toBeNull();
    });

    it('splits a full legacy ToothName string, keeping non-tooth markers', function () {
        $result = Teeth::fromLegacyString('_3UR,_EUL,_6LL,_bitewing');

        expect($result['teeth'])->toBe(['13', '65', '36'])
            ->and($result['flags'])->toBe(['bitewing']);
    });

    it('de-duplicates repeated teeth', function () {
        expect(Teeth::fromLegacyString('_3UR,_3UR,_3UR')['teeth'])->toBe(['13']);
    });

    it('tolerates empty and malformed input', function () {
        expect(Teeth::fromLegacyString(null)['teeth'])->toBe([])
            ->and(Teeth::fromLegacyString('')['teeth'])->toBe([])
            ->and(Teeth::fromLegacyString(',,, ,')['teeth'])->toBe([]);
    });

    it('every produced code is a valid catalogue entry', function () {
        foreach (['UR', 'UL', 'LL', 'LR'] as $quadrant) {
            foreach ([...range(1, 8), 'A', 'B', 'C', 'D', 'E'] as $tooth) {
                $code = Teeth::fromLegacyToken("_{$tooth}{$quadrant}");
                expect(Teeth::isValid($code))->toBeTrue("token _{$tooth}{$quadrant} produced {$code}");
            }
        }
    });
});

describe('jalali dates', function () {
    it('parses the formats the legacy app wrote', function () {
        expect(JalaliDate::parse('1403/05/12')?->toDateString())->toBe('2024-08-02')
            ->and(JalaliDate::parse('۱۴۰۳/۰۵/۱۲')?->toDateString())->toBe('2024-08-02')
            ->and(JalaliDate::parse('1403-5-2')?->toDateString())->toBe('2024-07-23')
            ->and(JalaliDate::parse('1403/05/12 14:30:00')?->toDateString())->toBe('2024-08-02');
    });

    it('returns null instead of throwing on bad input', function () {
        expect(JalaliDate::parse(null))->toBeNull()
            ->and(JalaliDate::parse(''))->toBeNull()
            ->and(JalaliDate::parse('garbage'))->toBeNull()
            ->and(JalaliDate::parse('1403/13/01'))->toBeNull()
            ->and(JalaliDate::parse('1403/00/10'))->toBeNull();
    });

    it('respects Jalali leap years', function () {
        // 1403 is a leap year (Esfand has 30 days); 1402 is not.
        expect(JalaliDate::parse('1403/12/30')?->toDateString())->toBe('2025-03-20')
            ->and(JalaliDate::parse('1402/12/30'))->toBeNull();
    });

    it('round-trips a date through format and parse', function () {
        foreach (['2024-08-02', '2020-03-20', '2025-01-01', '1999-12-31'] as $iso) {
            expect(JalaliDate::parse(JalaliDate::format($iso))?->toDateString())->toBe($iso);
        }
    });

    it('normalises Persian and Arabic digits', function () {
        expect(JalaliDate::toEnglishDigits('۱۴۰۳'))->toBe('1403')
            ->and(JalaliDate::toEnglishDigits('١٤٠٣'))->toBe('1403');
    });
});

describe('legacy permission mapping', function () {
    it('maps every legacy flag to real permissions', function () {
        $known = Permissions::all();

        foreach (Permissions::legacyMap() as $flag => $granted) {
            expect($granted)->not->toBeEmpty("flag {$flag} maps to nothing");

            foreach ($granted as $permission) {
                expect($known)->toContain($permission);
            }
        }
    });

    it('only grants permissions that exist in the catalogue', function () {
        $known = Permissions::all();

        foreach (Permissions::legacyBaseline() as $permission) {
            expect($known)->toContain($permission);
        }

        foreach (Permissions::roles() as $role => $granted) {
            foreach ($granted as $permission) {
                expect($known)->toContain($permission);
            }
        }
    });

    it('never grants a permission name twice within a role', function () {
        foreach (Permissions::roles() as $role => $granted) {
            expect($granted)->toBe(array_values(array_unique($granted)), "role {$role} has duplicates");
        }
    });
});
