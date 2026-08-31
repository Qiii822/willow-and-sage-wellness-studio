<?php
declare(strict_types=1);

use Cake\Utility\Text;
use Migrations\BaseSeed;

/**
 * The studio's treatment menu, as listed on its Square booking page.
 *
 * Seeded rather than left to be typed in so every teammate's install shows
 * the same menu, and so the layout can be judged against the real thing
 * instead of placeholder rows.
 *
 * Runs only when the table is empty. Once the studio has edited the menu
 * from the dashboard, those edits are the source of truth, and re-running
 * this must not overwrite or duplicate them.
 */
class ServicesSeed extends BaseSeed
{
    /**
     * @return void
     */
    public function run(): void
    {
        $table = $this->table('services');

        if ($this->fetchRow('SELECT COUNT(*) AS total FROM services')['total'] > 0) {
            // Already populated; leave whatever is there alone.
            return;
        }

        $now = date('Y-m-d H:i:s');
        $position = 0;
        $rows = [];

        foreach ($this->menu() as $category => $treatments) {
            foreach ($treatments as $treatment) {
                [$name, $description, $priceCents, $minutes] = $treatment;

                $rows[] = [
                    'id' => Text::uuid(),
                    'category' => $category,
                    'name' => $name,
                    'description' => $description,
                    'price_cents' => $priceCents,
                    'currency' => 'AUD',
                    'duration_minutes' => $minutes,
                    'position' => $position++,
                    'visible' => true,
                    'square_catalog_id' => null,
                    'created_at' => $now,
                    'modified' => $now,
                ];
            }
        }

        $table->insert($rows)->save();
    }

    /**
     * The menu itself, in the order the booking page lists it.
     *
     * Each treatment is [name, description, price in cents, minutes].
     * A null price is one Square shows as varying, and is rendered as
     * "Price varies" rather than as a missing line.
     *
     * @return array<string, array<int, array{0: string, 1: string, 2: int|null, 3: int|null}>>
     */
    protected function menu(): array
    {
        return [
            'Packages and first visit' => [
                [
                    'The Willow Ritual',
                    'The Willow Ritual offers a serene experience combining a Hot Stone '
                        . 'Massage and a Deluxe Pedicure, designed to provide full-body '
                        . 'relaxation and rejuvenation.',
                    17000,
                    135,
                ],
                [
                    'Restore & Renew massage and nail treatment',
                    'The Restore & Renew service includes a 60-minute full-body massage '
                        . 'designed to revitalize and relax, paired with a natural nail '
                        . 'treatment to leave you refreshed and polished.',
                    15000,
                    120,
                ],
                [
                    'The Ground & Glow',
                    'The Ground & Glow service combines a 60-minute massage and a '
                        . '60-minute facial, designed to promote relaxation and leave your '
                        . 'skin hydrated and radiant.',
                    16500,
                    135,
                ],
                [
                    'The Tranquility Trio Retreat',
                    'Experience the Tranquility Trio Retreat: 30 minutes of soothing '
                        . 'relaxation massage, a 45-minute organic facial, and a warm oil '
                        . 'scalp massage for 15 minutes.',
                    15500,
                    90,
                ],
            ],

            'Massage' => [
                [
                    'Warm Oil Scalp & Hair Ritual - add on',
                    'Add a nurturing wellness ritual to your massage or facial.',
                    3000,
                    15,
                ],
                [
                    'Hot Stone add on',
                    'Enhance your appointment with a Hot Stone add-on, designed to promote '
                        . 'deep relaxation, relieve muscle tension, and improve circulation.',
                    1500,
                    15,
                ],
                [
                    'Hot Stone Massage - 75 mins',
                    'The 75-minute Hot Stone Massage combines smooth, heated stones with '
                        . 'expert techniques to promote deep relaxation, relieve muscle '
                        . 'tension, and improve circulation.',
                    11000,
                    75,
                ],
                [
                    'Relaxation Massage - 90 min',
                    'Experience 90 minutes of tailored relaxation with a light to medium '
                        . 'pressure massage designed to relieve stress, ease muscle tension, '
                        . 'and promote overall rejuvenation.',
                    12000,
                    90,
                ],
                [
                    '60 min Relaxation',
                    'The 60-minute Relaxation service combines light to medium pressure '
                        . 'techniques with calming music to create a nurturing and '
                        . 'meditative experience.',
                    8500,
                    60,
                ],
                [
                    'Bamboo Fusion Massage - 75 mins',
                    'The 75-minute Bamboo Fusion Massage provides a deeply relaxing '
                        . 'experience combining light to firm pressure with the natural '
                        . 'benefits of eco-friendly bamboo tools.',
                    11000,
                    75,
                ],
            ],

            'Organic Facials' => [
                [
                    'Organic Glow Facial - 60 mins',
                    'The Organic Glow Facial is a 60-minute service designed to rejuvenate '
                        . 'your skin using natural, high-quality ingredients.',
                    9500,
                    60,
                ],
                [
                    'Willow Rejuvenation Facial - 75 mins',
                    'The Willow Rejuvenation Facial is a 75-minute treatment designed to '
                        . 'revitalize your skin through advanced techniques and premium '
                        . 'products.',
                    12000,
                    75,
                ],
                [
                    'Express facial - 30 min',
                    'The 30-minute Express Facial is designed for those seeking quick yet '
                        . 'effective skin rejuvenation.',
                    6000,
                    30,
                ],
            ],

            'Natural Nails, Hands and Feet' => [
                [
                    'Buff And Polish',
                    'Enjoy beautifully refreshed nails with professional nail shaping and '
                        . 'cuticle care, followed by a nourishing vitamin dose treatment and '
                        . 'soothing warm towel compress.',
                    3000,
                    30,
                ],
                [
                    'EVO Strength & Shine Finish - add on',
                    'The EVO Strength & Shine Finish service enhances your manicure or '
                        . 'pedicure with a breathable cured color system designed to deliver '
                        . 'long-lasting shine while promoting natural nail health.',
                    2500,
                    15,
                ],
                [
                    'Deluxe Natural Manicure - 1 hr',
                    'A nurturing manicure designed to refresh and care for your natural '
                        . 'nails and hands.',
                    6000,
                    60,
                ],
                [
                    'Deluxe Natural Pedicure - 60 mins',
                    'The Deluxe Natural Pedicure offers a 60-minute relaxing experience '
                        . 'designed to rejuvenate your feet with nourishing ingredients.',
                    7000,
                    60,
                ],
                [
                    'Strength & Restore Program (3 treatment Program)',
                    'The Strength & Restore Program is a natural nail restoration service '
                        . 'designed to promote healthier, stronger nails.',
                    20000,
                    75,
                ],
                [
                    'Paraffin Wax add on',
                    'Enhance your appointment with a paraffin wax add-on, designed to '
                        . 'deeply hydrate and lock in long-lasting moisture for your hands '
                        . 'or feet.',
                    1500,
                    30,
                ],
                [
                    'Willow & Sage Bio Sculpture EVO Restoration Ritual - up to 75 mins',
                    'A restorative manicure combining detailed natural nail and cuticle '
                        . 'care with a professional Bio Sculpture EVO gel finish.',
                    // Square lists this one as varying rather than fixed.
                    null,
                    60,
                ],
                [
                    'Natural Nail Recovery Treatment - 30 mins',
                    'A focused treatment for weakened, dry or damaged natural nails.',
                    4500,
                    30,
                ],
            ],

            'Waxing and Tinting' => [
                [
                    'Half leg Wax',
                    'Our half-leg waxing service ensures smooth, hair-free skin with a '
                        . 'gentle application process and long-lasting results.',
                    3500,
                    30,
                ],
                [
                    'Eyebrow + Lip wax',
                    'Our Eyebrow + Lip Wax service offers precise application and gentle '
                        . 'care, ensuring smooth, long-lasting results tailored to your '
                        . 'preferences.',
                    3000,
                    20,
                ],
                [
                    'Lash Tint',
                    'Enhance your natural lashes with our professional lash tint service, '
                        . 'designed to provide vibrant, long-lasting color.',
                    2500,
                    30,
                ],
                [
                    'Eyebrow Tint',
                    'Our eyebrow tinting service enhances your natural brows with '
                        . 'long-lasting color, providing a fuller and defined look tailored '
                        . 'to your preferences.',
                    2000,
                    15,
                ],
                [
                    'Brow + Lash Tint',
                    'Our Brow + Lash Tint service enhances your natural beauty by adding '
                        . 'depth and definition to your brows and lashes with long-lasting '
                        . 'results.',
                    4000,
                    30,
                ],
                [
                    'Eyebrow Shape',
                    'Achieve perfectly groomed eyebrows with our professional shaping '
                        . 'service, designed to create a defined arch and flawless symmetry '
                        . 'while maintaining a natural look.',
                    2000,
                    15,
                ],
                [
                    'Full Leg Wax',
                    'Our Full Leg Wax service provides smooth, hair-free skin with '
                        . 'long-lasting results, using professional-grade wax for maximum '
                        . 'comfort and effectiveness.',
                    5500,
                    45,
                ],
                [
                    'Underarm Wax',
                    'Our professional underarm waxing service ensures smooth, long-lasting '
                        . 'results with gentle care for sensitive skin.',
                    2500,
                    15,
                ],
                [
                    'Lip or Chin',
                    'Our Lip or Chin waxing service provides a quick and gentle solution '
                        . 'for smooth, hair-free skin.',
                    1500,
                    15,
                ],
            ],
        ];
    }
}
