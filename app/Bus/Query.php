<?php

namespace App\Bus;

/**
 * A request to read the application's state. Its handler, "<Query>Handler", returns plain data: a result object,
 * an enum, a scalar or null, never an Eloquent model (adopt-cqrs, design D2 and D4).
 *
 * @template TResult
 */
interface Query {}
