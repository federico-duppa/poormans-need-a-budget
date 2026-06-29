<?php

it('shows the public landing with the app brand', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee(config('app.name'));
    $response->assertSee('Presupuesto familiar');
});
