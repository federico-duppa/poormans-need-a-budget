<?php

it('muestra la landing pública con la marca de la app', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee(config('app.name'));
    $response->assertSee('Presupuesto familiar');
});
