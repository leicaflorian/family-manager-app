<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * I test di Pest in Feature partono da un database vuoto e usano il TestCase
 * dell'app. I test a classi dello starter kit dichiarano già da soli quello
 * che serve.
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
 * Anche i test in Unit hanno l'app avviata: chi usa il database aggiunge
 * RefreshDatabase nel proprio file.
 */
pest()->extend(TestCase::class)
    ->in('Unit');
