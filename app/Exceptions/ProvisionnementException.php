<?php

namespace App\Exceptions;

use RuntimeException;

/** Refus explicite lors de l'ouverture d'un compte depuis RHFlow ; le code est le statut HTTP renvoyé. */
class ProvisionnementException extends RuntimeException
{
}
