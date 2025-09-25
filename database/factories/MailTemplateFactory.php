<?php

namespace Database\Factories;

use App\Models\MailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MailTemplate>
 */
class MailTemplateFactory extends Factory
{
    protected $model = MailTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->slug(2),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['welcome', 'otp', 'invitation', 'reset_password']),
            'subject' => $this->faker->sentence().' {{user_name}}',
            'language' => $this->faker->randomElement(['de', 'en']),
            'body' => '<h1>'.$this->faker->sentence().'</h1><p>Hello {{user_name}}, '.$this->faker->paragraph().'</p>',
            'body_text' => $this->faker->sentence()."\n\nHello {{user_name}}, ".$this->faker->paragraph(),
            'category' => $this->faker->randomElement(['notification', 'authentication', 'invitation', 'system']),
            'language' => 'de',
            'variables' => [
                'user_name' => 'Name of the user',
                'test_var' => 'Test variable',
            ],
            'metadata' => [
                'created_by' => 'factory',
                'version' => '1.0',
            ],
            'is_active' => true,
            'is_system' => false,
            'is_customized' => false,
        ];
    }

    /**
     * Indicate that the template is a system template.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the template is customized.
     */
    public function customized(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_customized' => true,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific category for the template.
     */
    public function category(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    /**
     * Set a specific language for the template.
     */
    public function language(string $language): static
    {
        return $this->state(fn (array $attributes) => [
            'language' => $language,
        ]);
    }
}
