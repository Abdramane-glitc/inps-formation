<?php

namespace Tests\Unit;

use App\Enums\StatutBesoin;
use App\Enums\StatutInscription;
use App\Enums\StatutPresence;
use App\Support\CataloguePermissions;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_libelles_francais_pour_chaque_statut(): void
    {
        foreach ([StatutBesoin::class, StatutInscription::class, StatutPresence::class] as $e) {
            foreach ($e::cases() as $c) {
                $this->assertNotSame('', $c->libelle());
                $this->assertNotSame('', $c->couleur());
            }
            $this->assertCount(count($e::cases()), $e::options());
        }
        $this->assertSame('À compléter', StatutBesoin::ACompleter->libelle());
    }

    public function test_roles_proposes_ne_referencent_que_des_permissions_existantes(): void
    {
        foreach (CataloguePermissions::ROLES as $code => [, , $perms]) {
            foreach ($perms as $p) {
                $this->assertArrayHasKey($p, CataloguePermissions::PERMISSIONS, "$code → $p");
            }
        }
        $this->assertCount(7, CataloguePermissions::ROLES);
        // Aucun rôle ne détient toutes les permissions (pas de super-administrateur universel)
        foreach (CataloguePermissions::ROLES as [, , $perms]) {
            $this->assertLessThan(count(CataloguePermissions::PERMISSIONS), count($perms));
        }
    }
}
