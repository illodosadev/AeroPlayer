<?php

namespace App\Bus;

/**
 * A request to change the application's state. Its handler, "<Command>Handler", returns nothing and reports a
 * failure by throwing a UseCaseFailed exception (adopt-cqrs, design D2 and D3).
 */
interface Command {}
