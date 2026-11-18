<?php

declare(strict_types=1);

test('it answers the version of the server', function (): void {
    config(['app.version' => '2.3.0']);

    $response = $this->getJson(route('version'));

    $response->assertOk()->assertExactJson(['version' => '2.3.0']);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('it opens no session', function (): void {
    $response = $this->getJson(route('version'));

    $response->assertOk();
    expect($response->headers->getCookies())->toBeEmpty();
});
