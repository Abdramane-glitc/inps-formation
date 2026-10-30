<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function test_montant_en_fcfa(): void
    {
        $this->assertSame("1\u{202F}250\u{202F}000\u{00A0}FCFA", fcfa(1250000));
        $this->assertSame('—', fcfa(null));
        $this->assertSame("12\u{202F}500", fcfa(12500.4, false));
    }

    public function test_date_et_pourcentage(): void
    {
        $this->assertSame('05/10/2026', date_fr('2026-10-05'));
        $this->assertSame('—', date_fr(null));
        $this->assertSame("83,3\u{00A0}%", pourcent(83.333));
        $this->assertSame('—', pourcent(null));
    }
}
