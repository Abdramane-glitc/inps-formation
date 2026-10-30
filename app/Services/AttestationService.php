<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Exceptions\RegleMetierException;
use App\Models\Attestation;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Émission des attestations : formation terminée et participant ayant suivi la formation (version 1.5.0).
 */
class AttestationService
{
    /** @return list<string> conditions non remplies (vide = émission possible) */
    public function conditionsNonRemplies(Inscription $i): array
    {
        $manque = [];
        $i->loadMissing(['session', 'attestation']);
        if (in_array($i->statut, [StatutInscription::Desiste, StatutInscription::Remplace, StatutInscription::Annule, StatutInscription::ListeAttente], true)) {
            $manque[] = "L'inscription n'est pas active ({$i->statut->libelle()}).";
        }
        if (! $i->session->presencesValidees()) {
            $manque[] = 'La formation n\'est pas encore marquée comme terminée.';
        }
        if ($i->resultat !== 'achevee') {
            $manque[] = 'L\'agent n\'a pas suivi la formation.';
        }
        if ($i->attestation && $i->attestation->estValide()) {
            $manque[] = 'Une attestation valide existe déjà ('.$i->attestation->reference.').';
        }

        return $manque;
    }

    public function emettre(Inscription $i, User $user): Attestation
    {
        if ($manque = $this->conditionsNonRemplies($i)) {
            throw new RegleMetierException('Attestation non délivrable : '.implode(' ', $manque));
        }

        return DB::transaction(function () use ($i, $user) {
            $a = $i->attestation ?? new Attestation;
            $a->inscription_id = $i->id;
            $a->reference = GenerateurReference::suivante('attestations', 'reference', 'ATT', (int) now()->year);
            $a->code_verification = bin2hex(random_bytes(16));
            $a->emise_le = now();
            $a->emise_par = $user->id;
            $a->annulee_le = null;
            $a->motif_annulation = null;
            $a->save();
            Audit::enregistrer('attestation.emission', $a, "Émission de l'attestation {$a->reference}");

            return $a;
        });
    }

    public function annuler(Attestation $a, string $motif, User $user): Attestation
    {
        if (! $a->estValide()) {
            throw new RegleMetierException('Cette attestation est déjà annulée.');
        }
        if (trim($motif) === '') {
            throw new RegleMetierException('Le motif d\'annulation est obligatoire.');
        }
        $a->annulee_le = now();
        $a->motif_annulation = $motif;
        $a->save();
        Audit::enregistrer('attestation.annulation', $a, "Annulation de l'attestation {$a->reference}", ['motif' => mb_substr($motif, 0, 200)], $user->id);

        return $a;
    }
}
