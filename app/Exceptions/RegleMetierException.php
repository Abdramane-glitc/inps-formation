<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violation d'une règle métier (message en français destiné à l'utilisateur).
 * Rendue de manière centralisée (bootstrap/app.php) : retour au formulaire avec le message.
 */
class RegleMetierException extends RuntimeException {}
