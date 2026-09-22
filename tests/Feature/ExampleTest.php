<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    #[Test]
    public function the_root_url_sends_you_to_the_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    #[Test]
    public function the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
