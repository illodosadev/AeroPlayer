<?php

namespace App\Application;

use RuntimeException;

/**
 * Why a command could not complete (adopt-cqrs, design D3). Each context's failures extend it. The entry point
 * catches the specific failure and turns it into the page message, the validation error or the console line.
 *
 * Messages are fixed texts: never user input, a token or a response body. It is not named DomainException because
 * PHP already has a global \DomainException.
 */
abstract class UseCaseFailed extends RuntimeException {}
