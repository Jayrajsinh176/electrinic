<?php
define('DB_OPTIONAL', true);
/**
 * ELECTRINC — Clean Energy. Brighter Business.
 * Public EV Product Landing Page — Modern Clean Automotive Design
 */
$products = [
    // E-Rickshaw & E-Loader Fleet (Brochure & Studio Showroom)
    ['img' => 'auto-l3.jpeg',                       'cat' => 'rickshaw', 'name' => 'Auto L-3',                              'tag' => 'Passenger E-Rickshaw · L-3',    'range' => '90–100 KM+',   'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H', 'charge' => '3–4 Hrs'],
    ['img' => 'erikshaw-solar.jpeg',                'cat' => 'rickshaw', 'name' => 'E-Rickshaw with Solar',                  'tag' => 'Passenger · Solar Roof',        'range' => '100 KM+',      'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H', 'charge' => 'Solar + 3 Hrs'],
    ['img' => 'mini-eloader.jpeg',                  'cat' => 'rickshaw', 'name' => 'Mini E-Loader',                          'tag' => 'Cargo · LXI / VXI / ZXI',        'range' => '70–120 KM+',   'battery' => '51V / 45–75AH Lithium',  'warranty' => '18 Months', 'speed' => '25 KM/H', 'charge' => '3–4 Hrs'],
    ['img' => 'eloader-solar.jpeg',                 'cat' => 'rickshaw', 'name' => 'E-Loader with Solar',                    'tag' => 'Cargo · Solar Roof',            'range' => '100 KM+',      'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H', 'charge' => 'Solar + 3 Hrs'],
    ['img' => 'rickshaw-passenger-deluxe.png',      'cat' => 'rickshaw', 'name' => 'PASSENGER E-RICKSHAW DELUXE (White & Blue)', 'tag' => 'Passenger 4+1 · Heavy Chassis', 'range' => '100–115 KM+',  'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H', 'charge' => '3–4 Hrs'],
    ['img' => 'rickshaw-cargo-box-blue.png',        'cat' => 'rickshaw', 'name' => 'E-LOADER MAX CONTAINER (Cargo Box Blue)',    'tag' => 'Closed Delivery Cargo Box',     'range' => '100–120 KM+',  'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H', 'charge' => '3–4 Hrs'],
    ['img' => 'rickshaw-auto-l5-white.png',         'cat' => 'rickshaw', 'name' => 'E-AUTO L-5 PASSENGER (Flagship White)',      'tag' => 'Commercial Auto · High Payload', 'range' => '120–140 KM+',  'battery' => '51.2V / 120AH Lithium', 'warranty' => '36 Months', 'speed' => '35 KM/H', 'charge' => '3.5 Hrs'],
    ['img' => 'rickshaw-mini-loader-bed.png',       'cat' => 'rickshaw', 'name' => 'MINI E-LOADER OPEN BED (Glossy Black)',      'tag' => 'Open Drop-Side Utility Bed',    'range' => '80–100 KM+',   'battery' => '51V / 60AH Lithium',    'warranty' => '18 Months', 'speed' => '25 KM/H', 'charge' => '3 Hrs'],
    // Original Brochure Fleet
    ['img' => 'max-vxi.jpeg',        'cat' => 'scooter',  'name' => 'MAX VXI',                      'tag' => 'City Scooter',            'range' => '55 KM+',       'battery' => '51V / 30AH Lithium',    'warranty' => '18 Months', 'speed' => '50 KM/H', 'charge' => '2.5 Hrs'],
    ['img' => 'evelocy-m2.jpeg',     'cat' => 'scooter',  'name' => 'MAX ZXI',                      'tag' => 'Sport Edition',           'range' => '75–100 KM+',   'battery' => '60V / 35AH Lithium',    'warranty' => '36 Months', 'speed' => '60 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'active-chetaak.jpeg', 'cat' => 'scooter',  'name' => 'ACTIVE CHETAAK (Finance)',     'tag' => 'Commercial Easy EMI',       'range' => '60–110 KM+',   'battery' => '60V / 45AH Lithium',    'warranty' => '36 Months', 'speed' => '55 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'active-chetaak.jpeg', 'cat' => 'scooter',  'name' => 'ACTIVE CHETAAK (Without Finance)', 'tag' => 'Instant Handover Edition', 'range' => '60–120 KM+', 'battery' => '60V / 45AH Lithium',    'warranty' => '36 Months', 'speed' => '55 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'mania-zxi.jpeg',      'cat' => 'scooter',  'name' => 'MANIA VXI',                    'tag' => 'Sport Scooter',           'range' => '135–150 KM+',  'battery' => '60V / 60AH Lithium',    'warranty' => '18 Months', 'speed' => '70 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'evelocy-m2.jpeg',     'cat' => 'scooter',  'name' => 'M2 ZXI',                       'tag' => 'Premium · Dual Disc Brakes', 'range' => '125–150 KM+', 'battery' => '3.0KW / 51V/60AH Lithium', 'warranty' => '36 Months', 'speed' => '65 KM/H', 'charge' => '2.5–3 Hrs'],
    ['img' => 'dmi.jpeg',            'cat' => 'scooter',  'name' => 'DMI',                          'tag' => 'Value Scooter',           'range' => '40–50 KM+',    'battery' => 'Lead Acid / Gel',       'warranty' => '12 Months', 'speed' => '45 KM/H', 'charge' => '4–5 Hrs'],
    // New Studio Showroom Fleet
    ['img' => 'scooter-silver-aero.png',    'cat' => 'scooter', 'name' => 'MAX VXI (Silver Aero Edition)',   'tag' => 'Super Sport · Twin LED Eyes', 'range' => '65–80 KM+',    'battery' => '51V / 30AH Lithium', 'warranty' => '36 Months', 'speed' => '55 KM/H', 'charge' => '2.5 Hrs'],
    ['img' => 'scooter-beast-pro.png',      'cat' => 'scooter', 'name' => 'BEAST PRO (Emerald Edition)',     'tag' => 'Dual Projector LED · Sport',  'range' => '80–95 KM+',    'battery' => '60V / 35AH Lithium', 'warranty' => '36 Months', 'speed' => '65 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'scooter-beast-pro-plus.png', 'cat' => 'scooter', 'name' => 'BEAST PRO+ (Army Green Edition)', 'tag' => 'Heavy Duty · Wide Headlamp', 'range' => '85–100 KM+',   'battery' => '60V / 40AH Lithium', 'warranty' => '36 Months', 'speed' => '65 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'scooter-chetaak-red.png',    'cat' => 'scooter', 'name' => 'ACTIVE CHETAAK PRO (Crimson Red)', 'tag' => 'Heavy Steel Guard · All-Weather', 'range' => '75–90 KM+', 'battery' => '60V / 35AH Lithium', 'warranty' => '36 Months', 'speed' => '55 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'scooter-chetaak-mint.png',   'cat' => 'scooter', 'name' => 'ACTIVE CHETAAK (Classic Mint)',  'tag' => 'Chrome Chevron · Family Commuter', 'range' => '70–85 KM+', 'battery' => '60V / 32AH Lithium', 'warranty' => '36 Months', 'speed' => '55 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'scooter-mania-orange.png',   'cat' => 'scooter', 'name' => 'ELECTER MANIA (Sunset Orange)',   'tag' => 'E-Velocy Official · High Torque', 'range' => '90–110 KM+', 'battery' => '60V / 42AH Lithium', 'warranty' => '36 Months', 'speed' => '70 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'scooter-m2-sage.png',        'cat' => 'scooter', 'name' => 'E-VELOCY M2 (Sage Tri-LED)',      'tag' => 'Futuristic Triple Lens Bar',  'range' => '100–120 KM+',  'battery' => '60V / 45AH Lithium', 'warranty' => '36 Months', 'speed' => '75 KM/H', 'charge' => '2.5–3 Hrs'],
    // Sports E-Bike Fleet (Brochure & Studio Showroom)
    ['img' => 'bike-v6.jpeg',               'cat' => 'bike', 'name' => 'Bike V6 2.0',                   'tag' => 'Sports E-Bike · Track Ready',   'range' => '120 KM+',      'battery' => '72V / 60AH Lithium', 'warranty' => '36 Months', 'speed' => '85 KM/H',  'charge' => '3 Hrs'],
    ['img' => 'bike-kn1.jpeg',              'cat' => 'bike', 'name' => 'Bike KN1',                      'tag' => 'Commuter E-Bike · High Torque', 'range' => '100 KM+',      'battery' => '72V / 45AH Lithium', 'warranty' => '36 Months', 'speed' => '75 KM/H',  'charge' => '3 Hrs'],
    ['img' => 'bike-ninja-red.png',         'cat' => 'bike', 'name' => 'BIKE V6 SUPERSPORT (Fire Red)', 'tag' => 'Full Aerodynamic Fairing',     'range' => '130–150 KM+',  'battery' => '72V / 60AH Lithium', 'warranty' => '36 Months', 'speed' => '105 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'bike-ninja-green.png',       'cat' => 'bike', 'name' => 'NINJA STEALTH EV (Monster Green)', 'tag' => 'Supersport Race Edition',    'range' => '140–160 KM+',  'battery' => '72V / 60AH Lithium', 'warranty' => '36 Months', 'speed' => '110 KM/H', 'charge' => '3 Hrs'],
    ['img' => 'bike-trellis-blue.png',      'cat' => 'bike', 'name' => 'STREETFIGHTER EV (Apex Blue)',  'tag' => 'Naked Red Trellis Frame',       'range' => '120–135 KM+',  'battery' => '72V / 50AH Lithium', 'warranty' => '36 Months', 'speed' => '95 KM/H',  'charge' => '2.5–3 Hrs'],
    ['img' => 'bike-commuter-blue.png',     'cat' => 'bike', 'name' => 'KN1 PRO COMMUTER (Royal Blue)', 'tag' => 'High Torque Commuter',         'range' => '110–125 KM+',  'battery' => '72V / 45AH Lithium', 'warranty' => '36 Months', 'speed' => '85 KM/H',  'charge' => '3 Hrs'],
];

$catLabels = ['rickshaw' => 'E-Rickshaw', 'scooter' => 'E-Scooter', 'bike' => 'E-Bike'];

$feedVideos = [];
if (file_exists(__DIR__ . '/member/config/db.php')) {
    @include_once __DIR__ . '/member/config/db.php';
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $vq = $conn->query("SELECT * FROM ind_electric_vehicles WHERE status = 'active' ORDER BY sort_order ASC, id ASC");
            if ($vq && $vq->num_rows > 0) {
                $dbProducts = [];
                while ($row = $vq->fetch_assoc()) {
                    $cat = in_array($row['category'], ['rickshaw', 'loader', 'auto'], true) ? 'rickshaw' : $row['category'];
                    $tag = !empty($row['has_bpr']) ? 'Brand Promotion Reward (BPR)' : 'Certified Lithium Edition';
                    $img = !empty($row['image_path']) ? $row['image_path'] : 'max-vxi.png';
                    $dbProducts[] = [
                        'img'      => $img,
                        'cat'      => $cat,
                        'name'     => $row['model_name'],
                        'tag'      => $tag,
                        'range'    => !empty($row['range_km']) ? $row['range_km'] : '50–60 KM+',
                        'battery'  => !empty($row['battery_spec']) ? $row['battery_spec'] : 'Lithium / Gel',
                        'warranty' => ((int)($row['warranty_months'] ?? 36)) . ' Months',
                        'speed'    => !empty($row['top_speed']) ? $row['top_speed'] : '50 KM/H',
                        'charge'   => !empty($row['charging_time']) ? $row['charging_time'] : '3–4 Hrs',
                        'price'    => (float)$row['vehicle_price']
                    ];
                }
                if (!empty($dbProducts)) {
                    $products = $dbProducts;
                }
            }
        } catch (\Throwable $e) {}

        try {
            $feedQ = $conn->query("SELECT v.*, su.full_name, sp.vehicle_model, sp.registration_number 
                                   FROM ind_video_verifications v
                                   JOIN signup_users su ON su.customer_id = v.customer_id COLLATE utf8mb4_unicode_ci
                                   JOIN ind_scooter_promotions sp ON sp.id = v.promotion_id
                                   WHERE v.status = 'verified' AND v.show_on_landing_page = 1
                                   ORDER BY v.id DESC LIMIT 4");
            if ($feedQ) {
                while ($fv = $feedQ->fetch_assoc()) $feedVideos[] = $fv;
            }
        } catch (\Throwable $e) {
            $feedVideos = [];
        }
    }
}

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ELECTRINC — Clean Energy. Brighter Business.</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="ELECTRINC — a venture of Incredible Group of Companies. Premium electric rickshaws, scooters and bikes, with a pan-India franchise network.">
<link rel="icon" href="assets/electrinc-logo.png">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<style>
    :root {
        --saffron: #E0700C;
        --saffron-d: #B25400;
        --saffron-soft: #FFF3E7;
        --green: #0E7A3B;
        --green-d: #0A5E2D;
        --green-soft: #EAF5EC;
        --night: #0A1F16;
        --night-2: #123B27;
        --ink: #181E19;
        --ink-2: #525D54;
        --page: #FAF8F4;
        --line: #EBE5D8;
    }

    * { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    .font-mono-num { font-family: 'Space Grotesk', monospace; }
    html { scroll-behavior: smooth; }
    body { background: var(--page); color: var(--ink); overflow-x: hidden; }

    /* ================= KEYFRAMES & ANIMATIONS ================= */
    @keyframes marqueeRun {
        from { transform: translateX(0); }
        to { transform: translateX(-50%); }
    }
    @keyframes auroraOrbitA {
        0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.35; }
        50% { transform: scale(1.2) translate(40px, -30px); opacity: 0.65; }
    }
    @keyframes auroraOrbitB {
        0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.25; }
        50% { transform: scale(1.3) translate(-35px, 25px); opacity: 0.5; }
    }
    @keyframes specularLightGlint {
        0% { transform: translateX(-140%) skewX(-25deg); }
        100% { transform: translateX(240%) skewX(-25deg); }
    }
    @keyframes cardFadeScaleIn {
        from { opacity: 0; transform: scale(0.93) translateY(18px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }

    /* Ambient Hero Mesh Background */
    .hero-mesh {
        background-color: var(--night);
        background-image: 
            radial-gradient(at 0% 0%, rgba(224, 112, 12, 0.22) 0px, transparent 50%),
            radial-gradient(at 100% 100%, rgba(14, 122, 59, 0.35) 0px, transparent 60%),
            linear-gradient(175deg, #071710 0%, #0A1F16 45%, #0E2E1E 100%);
        position: relative;
        overflow: hidden;
    }
    .aurora-glow-1 {
        position: absolute; top: -140px; right: -80px; width: 560px; height: 560px;
        background: radial-gradient(circle, rgba(224,112,12,.4), transparent 65%);
        filter: blur(50px); animation: auroraOrbitA 9s ease-in-out infinite; pointer-events: none;
    }
    .aurora-glow-2 {
        position: absolute; bottom: -180px; left: -100px; width: 620px; height: 620px;
        background: radial-gradient(circle, rgba(16,185,129,.3), transparent 65%);
        filter: blur(55px); animation: auroraOrbitB 11s ease-in-out infinite; pointer-events: none;
    }
    .hero-spotlight-tracer {
        position: absolute; width: 520px; height: 520px; border-radius: 999px;
        background: radial-gradient(circle, rgba(240,164,79,.16), transparent 65%);
        pointer-events: none; z-index: 1; filter: blur(35px); opacity: 0;
        transition: opacity 0.4s ease; will-change: transform;
    }

    /* Buttons */
    .btn-brand-primary {
        background: linear-gradient(105deg, var(--saffron) 0%, var(--saffron-d) 100%);
        color: #fff;
        box-shadow: 0 12px 26px -8px rgba(224, 112, 12, 0.65);
        transition: transform .2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .2s ease;
        position: relative; overflow: hidden;
    }
    .btn-brand-primary::after {
        content: ''; position: absolute; top: 0; left: 0; width: 50%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
        transform: translateX(-150%) skewX(-20deg);
        animation: specularLightGlint 3.5s infinite;
        pointer-events: none;
    }
    .btn-brand-primary:hover {
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 18px 34px -8px rgba(224, 112, 12, 0.8);
    }
    .btn-brand-ghost {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fff;
        backdrop-filter: blur(10px);
        transition: all .2s ease;
    }
    .btn-brand-ghost:hover {
        background: rgba(255, 255, 255, 0.18);
        border-color: rgba(255, 255, 255, 0.45);
        transform: translateY(-2px);
    }

    /* 3D Showcase Turntable Stage */
    .stage-3d-wrap {
        perspective: 1200px;
        transform-style: preserve-3d;
        transition: transform 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    .stage-ground-shadow {
        position: absolute; bottom: -12px; left: 10%; right: 10%; height: 26px; border-radius: 50%;
        background: radial-gradient(ellipse at center, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.3) 50%, transparent 75%);
        filter: blur(7px); pointer-events: none; z-index: 1;
    }

    /* Hero Slides & Scan-Wipe Transition */
    .hero-slide-wrap { position: relative; aspect-ratio: 604/724; transform-style: preserve-3d; }
    .hero-slide {
        position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
        opacity: 0;
        pointer-events: none;
    }
    .hero-slide.active { opacity: 1; pointer-events: auto; z-index: 2; }
    .hero-slide.scanning-in { opacity: 1; z-index: 10; pointer-events: none; }

    /* Laser Scan-Wipe Beam (Only active during slide transition) */
    .laser-scan-beam {
        position: absolute;
        left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(240,164,79,0.5) 15%, #34D399 45%, #FFFFFF 50%, #34D399 55%, rgba(240,164,79,0.5) 85%, transparent);
        box-shadow: 0 0 16px #34D399, 0 0 28px #10B981, 0 0 8px #FFFFFF;
        pointer-events: none;
        z-index: 30;
        transform: translateY(-50%);
    }

    /* Marquee Infinite Banner */
    .marquee-track {
        display: flex; width: 200%;
        animation: marqueeRun 24s linear infinite;
    }
    .marquee-track:hover { animation-play-state: paused; }

    /* Bento Grid Studio Styling */
    .bento-card {
        background: #ffffff;
        border: 1px solid #E5DFD3;
        border-radius: 30px;
        box-shadow: 0 4px 18px -4px rgba(10, 31, 22, 0.05);
        transition: transform .35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .35s cubic-bezier(0.16, 1, 0.3, 1), border-color .25s ease;
        position: relative; overflow: hidden;
    }
    .bento-card:hover {
        transform: translateY(-8px) scale(1.01);
        box-shadow: 0 24px 50px -12px rgba(10, 31, 22, 0.14);
        border-color: rgba(14, 122, 59, 0.35);
    }
    .bento-card::after {
        content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 10;
        background: linear-gradient(115deg, transparent 35%, rgba(255,255,255,0.45) 50%, transparent 65%);
        transform: translateX(-150%); opacity: 0; transition: opacity .3s ease;
    }
    .bento-card:hover::after {
        opacity: 1; animation: specularLightGlint 0.9s cubic-bezier(0.16, 1, 0.3, 1);
    }

    /* Bento Custom Keyframe Animations */
    @keyframes cellChargeWave {
        0%, 100% { height: 35%; opacity: 0.6; }
        50% { height: 95%; opacity: 1; }
    }

    /* Automotive Studio Cards */
    .studio-card {
        background: #ffffff;
        border: 1px solid #E5E0D5;
        border-radius: 28px;
        overflow: hidden;
        box-shadow: 0 4px 20px -2px rgba(10, 31, 22, 0.05), 0 1px 3px 0 rgba(0, 0, 0, 0.02);
        transition: transform .35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .35s cubic-bezier(0.16, 1, 0.3, 1), border-color .25s ease;
        position: relative;
    }
    .studio-card:hover {
        transform: translateY(-8px) scale(1.012);
        box-shadow: 0 24px 50px -12px rgba(10, 31, 22, 0.16), 0 12px 24px -8px rgba(224, 112, 12, 0.12);
        border-color: rgba(14, 122, 59, 0.4);
    }
    .studio-card .zoom-img { transition: transform .6s cubic-bezier(0.16, 1, 0.3, 1); }
    .studio-card:hover .zoom-img { transform: scale(1.05); }
    .studio-card::before {
        content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 10;
        background: linear-gradient(105deg, transparent 38%, rgba(255,255,255,.6) 50%, transparent 62%);
        transform: translateX(-120%); opacity: 0; transition: opacity .3s ease;
    }
    .studio-card:hover::before { opacity: 1; animation: specularLightGlint .85s cubic-bezier(0.16, 1, 0.3, 1); }

    /* Laser Cyber-Sweep Diagnostics on Card Hover */
    @keyframes cardLaserSweep {
        0% { top: -5%; opacity: 0; }
        15% { opacity: 0.95; }
        85% { opacity: 0.95; }
        100% { top: 105%; opacity: 0; }
    }
    .card-laser-beam {
        position: absolute;
        left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, rgba(224, 112, 12, 0.4) 15%, #34D399 45%, #FFFFFF 50%, #34D399 55%, rgba(224, 112, 12, 0.4) 85%, transparent);
        box-shadow: 0 0 14px #34D399, 0 0 24px #10B981, 0 0 6px #FFFFFF;
        pointer-events: none;
        z-index: 25;
        opacity: 0;
    }
    .studio-card:hover .card-laser-beam {
        animation: cardLaserSweep 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* 3D Dynamic Glare Radial Reflection */
    .card-glare-overlay {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background: radial-gradient(circle 350px at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(255, 255, 255, 0.4), transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 2;
    }
    .studio-card-3d:hover .card-glare-overlay {
        opacity: 1;
    }

    /* Segmented Filter Pills */
    .filter-pill {
        color: #4A554D;
        background: transparent;
        transition: all .25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .filter-pill.active {
        background: linear-gradient(105deg, var(--night) 0%, var(--green-d) 100%) !important;
        color: #ffffff !important;
        box-shadow: 0 8px 20px -6px rgba(10, 31, 22, 0.5) !important;
    }
    .filter-pill:hover:not(.active) {
        background: rgba(255, 255, 255, 0.85);
        color: var(--night);
    }

    /* Range Slider */
    input[type=range]::-webkit-slider-thumb {
        appearance: none; width: 22px; height: 22px; border-radius: 999px;
        background: #E0700C; border: 3px solid #FFFFFF;
        box-shadow: 0 0 14px rgba(224, 112, 12, 0.8);
        cursor: pointer; transition: transform .15s ease;
    }
    input[type=range]::-webkit-slider-thumb:hover { transform: scale(1.2); }

    /* Scroll Reveal Engine */
    .reveal {
        opacity: 0;
        transition: opacity .85s cubic-bezier(0.16, 1, 0.3, 1), transform .85s cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
    }
    .reveal-up { transform: translateY(36px); }
    .reveal-down { transform: translateY(-36px); }
    .reveal-left { transform: translateX(-36px); }
    .reveal-right { transform: translateX(36px); }
    .reveal.is-revealed { opacity: 1; transform: translate(0) scale(1); }

    /* ================= PRO MOBILE-FIRST RESPONSIVE TUNING ================= */
    * { -webkit-tap-highlight-color: transparent; }
    html { -webkit-text-size-adjust: 100%; }

    /* Clean Touch Scroll Containers */
    .no-scrollbar::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Prevent mobile input zoom on iOS / Android */
    @media (max-width: 768px) {
        input, select, textarea {
            font-size: 16px !important;
        }
    }

    /* Disable aggressive 3D magnetic tilt on touch devices to prevent jitter */
    @media (max-width: 1024px) {
        .studio-card-3d:hover {
            transform: none !important;
        }
        .stage-3d-wrap {
            transform: none !important;
        }
    }

    /* Mobile Drawer Animation */
    @keyframes mobileDrawerSlide {
        from { opacity: 0; transform: translateY(-14px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .mobile-drawer-anim {
        animation: mobileDrawerSlide 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Safe Area Padding for Mobile Bottom Bar
       Applied to the footer (not body) so its dark background fills the space behind the dock */
    @media (max-width: 1023px) {
        footer#contact {
            padding-bottom: calc(2.5rem + 4.5rem + env(safe-area-inset-bottom, 0px));
        }
    }

</style>
</head>
<body class="overflow-x-hidden selection:bg-amber-500 selection:text-white">

<?php include __DIR__ . '/includes/preloader.php'; ?>

<!-- ============ LUXURY FLOATING GLASS CAPSULE NAVBAR ============ -->
<div class="fixed top-3 sm:top-4 inset-x-0 z-50 px-3 sm:px-6 pointer-events-none">
    <header class="max-w-7xl mx-auto h-[64px] sm:h-[68px] bg-white/95 backdrop-blur-xl border border-white/80 shadow-[0_12px_36px_-6px_rgba(10,31,22,0.14)] rounded-full px-4 sm:px-6 flex items-center justify-between pointer-events-auto transition-all">
        <!-- Brand Logo -->
        <a href="#top" class="flex items-center gap-2 min-w-0 mr-2 group">
            <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-7 sm:h-9 w-auto min-w-0 max-w-[120px] sm:max-w-none object-contain object-left transition-transform duration-300 group-hover:scale-105" style="filter: brightness(0) saturate(100%);">
        </a>

        <!-- Desktop Navigation Links -->
        <nav class="hidden lg:flex items-center gap-7 text-[13px] font-bold text-gray-700">
            <a href="#top" class="hover:text-emerald-700 transition">Home</a>
            <a href="#products" class="hover:text-emerald-700 transition">Showroom Fleet</a>
            <a href="#why-us" class="hover:text-emerald-700 transition">Technology</a>
            <a href="#franchise" class="hover:text-emerald-700 transition">Franchise</a>
            <a href="#contact" class="hover:text-emerald-700 transition">Contact</a>
        </nav>

        <!-- Right Action Hub (Clean & Uncluttered) -->
        <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
            <a href="member/login.php" class="hidden sm:inline-block text-xs font-bold px-4 py-2 rounded-full border border-gray-200 hover:bg-gray-50 text-gray-700 transition">
                Member Login
            </a>
            <a href="franchise/index.php" class="btn-brand-primary hidden lg:inline-flex text-xs font-bold px-5 py-2 rounded-full items-center gap-1.5 whitespace-nowrap shadow-md">
                <span>Franchise</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>

            <!-- Mobile Hamburger Toggle Button -->
            <button type="button" onclick="toggleMobileMenu();" class="lg:hidden shrink-0 w-9 h-9 rounded-full border border-gray-200 flex items-center justify-center text-gray-700 bg-white/80 hover:bg-gray-50 active:scale-95 transition shadow-xs" aria-label="Toggle Menu">
                <i data-lucide="menu" id="burgerIcon" class="w-4 h-4"></i>
                <i data-lucide="x" id="closeIcon" class="w-4 h-4 hidden"></i>
            </button>
        </div>
    </header>
</div>

<!-- ============ PRO MOBILE DRAWER BACKDROP & MENU ============ -->
<div id="mobileBackdrop" class="fixed inset-0 z-40 bg-[#0A1F16]/75 backdrop-blur-md hidden lg:hidden transition-opacity duration-300" onclick="toggleMobileMenu(false);"></div>

<div id="mobileMenu" class="fixed top-[86px] sm:top-[94px] inset-x-3 sm:inset-x-6 z-50 bg-[#0A1F16] border border-white/15 shadow-[0_24px_60px_rgba(0,0,0,0.55)] rounded-[28px] p-5 sm:p-6 hidden lg:hidden max-h-[calc(100vh-7rem)] overflow-y-auto no-scrollbar mobile-drawer-anim [&_svg]:shrink-0">
    <!-- Drawer Header -->
    <div class="flex items-center justify-between gap-2 pb-3.5 border-b border-white/10">
        <div class="flex items-center gap-2 min-w-0">
            <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-6 w-auto min-w-0 max-w-[110px] object-contain object-left">
            <span class="shrink-0 whitespace-nowrap text-[9px] font-extrabold uppercase tracking-wider text-emerald-300 bg-emerald-500/15 px-2 py-0.5 rounded-full border border-emerald-500/40">Official Portal</span>
        </div>
    </div>

    <!-- Navigation Links With Icons & Status Badges -->
    <div class="mt-3 flex flex-col gap-1 font-bold text-sm">
        <a href="#top" onclick="toggleMobileMenu(false);" class="flex items-center justify-between gap-2 py-2.5 px-3 rounded-2xl hover:bg-white/10 text-white transition">
            <span class="flex items-center gap-3">
                <i data-lucide="home" class="w-4 h-4 text-emerald-400"></i>
                <span>Home Showcase</span>
            </span>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-white/40"></i>
        </a>
        <a href="#products" onclick="toggleMobileMenu(false);" class="flex items-center justify-between gap-2 py-2.5 px-3 rounded-2xl hover:bg-white/10 text-white transition">
            <span class="flex items-center gap-3">
                <i data-lucide="layers" class="w-4 h-4 text-amber-400"></i>
                <span>Showroom Fleet</span>
            </span>
            <span class="shrink-0 whitespace-nowrap text-[10px] font-mono font-extrabold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/40">11 Models</span>
        </a>
        <a href="#why-us" onclick="toggleMobileMenu(false);" class="flex items-center justify-between gap-2 py-2.5 px-3 rounded-2xl hover:bg-white/10 text-white transition">
            <span class="flex items-center gap-3">
                <i data-lucide="cpu" class="w-4 h-4 text-sky-400"></i>
                <span>Technology & BMS</span>
            </span>
            <span class="shrink-0 whitespace-nowrap text-[10px] font-mono font-extrabold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/40">Lithium 4.0</span>
        </a>
        <a href="#franchise" onclick="toggleMobileMenu(false);" class="flex items-center justify-between gap-2 py-2.5 px-3 rounded-2xl hover:bg-white/10 text-white transition">
            <span class="flex items-center gap-3">
                <i data-lucide="handshake" class="w-4 h-4 text-emerald-400"></i>
                <span>Franchise Opportunity</span>
            </span>
            <span class="shrink-0 whitespace-nowrap text-[10px] font-mono font-extrabold px-2 py-0.5 rounded-full bg-emerald-600 text-white">Dealership</span>
        </a>
        <a href="#contact" onclick="toggleMobileMenu(false);" class="flex items-center justify-between gap-2 py-2.5 px-3 rounded-2xl hover:bg-white/10 text-white transition">
            <span class="flex items-center gap-3">
                <i data-lucide="phone-call" class="w-4 h-4 text-emerald-400"></i>
                <span>Contact & Network</span>
            </span>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-white/40"></i>
        </a>
    </div>

    <!-- Quick Action Portals Hub -->
    <div class="mt-4 pt-4 border-t border-white/10 grid grid-cols-2 gap-2">
        <a href="member/login.php" class="w-full text-center py-2.5 rounded-full border border-white/30 hover:bg-white/10 text-xs font-bold text-white transition">
            Member Login
        </a>
        <a href="franchise/index.php" class="w-full text-center py-2.5 rounded-full btn-brand-primary text-xs font-bold">
            Franchise Login
        </a>
    </div>
</div>

<!-- ============ HERO: CLEAN AUTOMOTIVE SHOWROOM DECK ============ -->
<section id="top" class="hero-mesh pt-28 sm:pt-36 pb-20 sm:pb-28 relative">
    <div class="aurora-glow-1"></div>
    <div class="aurora-glow-2"></div>
    <div class="hero-spotlight-tracer" id="heroSpotlight"></div>

    <div class="max-w-7xl mx-auto px-5 sm:px-6 relative z-10">
        <!-- Top Automotive Headline & Statement -->
        <div class="text-center max-w-3xl mx-auto reveal reveal-up">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wider text-white/90 bg-white/10 border border-white/20 backdrop-blur-md shadow-sm">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <i data-lucide="leaf" class="w-3.5 h-3.5 text-amber-400"></i> A Venture of Incredible Group of Companies
            </div>
            <h1 class="mt-5 text-[34px] sm:text-[52px] lg:text-[60px] font-black leading-[1.05] tracking-tight text-white">
                Drive the Future.<br>Drive <span class="bg-gradient-to-r from-amber-400 via-emerald-300 to-green-400 bg-clip-text text-transparent">Electric.</span>
            </h1>
            <p class="mt-4 text-[14px] sm:text-[17px] text-white/80 max-w-xl mx-auto leading-relaxed">
                India's next-generation lithium-powered electric rickshaws, scooters and sports bikes. Engineered for massive range, zero emissions, and high-margin franchise growth.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="#products" class="btn-brand-primary px-7 py-3 rounded-full text-xs sm:text-sm font-bold inline-flex items-center gap-2 group">
                    Explore Our Fleet <i data-lucide="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1"></i>
                </a>
                <a href="#contact" class="btn-brand-ghost px-7 py-3 rounded-full text-xs sm:text-sm font-bold inline-flex items-center gap-2">
                    <i data-lucide="phone-call" class="w-4 h-4 text-emerald-300"></i> Book a Test Ride
                </a>
            </div>
        </div>

        <!-- ============ CENTER SHOWCASE: 3D TURNTABLE + COCKPIT DIALS ============ -->
        <div class="mt-10 sm:mt-14 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            
            <!-- Left Cockpit Telemetry Widget (Lg: 3 cols) -->
            <div class="hidden lg:flex lg:col-span-3 flex-col gap-4 reveal reveal-left delay-75">
                <div class="p-5 rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md text-white">
                    <div class="flex items-center justify-between text-xs font-bold text-white/60 mb-2">
                        <span class="uppercase tracking-wider">Certified Range</span>
                        <i data-lucide="gauge" class="w-4 h-4 text-emerald-400"></i>
                    </div>
                    <div class="text-3xl font-black text-emerald-400 font-mono-num" id="cockpitRange">100 KM+</div>
                    <div class="text-[11px] text-white/60 mt-1">Real-world tested on Indian roads</div>
                </div>

                <div class="p-5 rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md text-white">
                    <div class="flex items-center justify-between text-xs font-bold text-white/60 mb-2">
                        <span class="uppercase tracking-wider">Battery Spec</span>
                        <i data-lucide="battery-charging" class="w-4 h-4 text-amber-400"></i>
                    </div>
                    <div class="text-base font-extrabold text-amber-300 font-mono-num" id="cockpitBattery">51.2V / 100AH Lithium</div>
                    <div class="text-[11px] text-white/60 mt-1">Smart BMS Thermal Protection</div>
                </div>

                <div class="p-5 rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md text-white">
                    <div class="flex items-center justify-between text-xs font-bold text-white/60 mb-2">
                        <span class="uppercase tracking-wider">Warranty Shield</span>
                        <i data-lucide="shield-check" class="w-4 h-4 text-sky-400"></i>
                    </div>
                    <div class="text-2xl font-black text-sky-300 font-mono-num" id="cockpitWarranty">36 Months</div>
                    <div class="text-[11px] text-white/60 mt-1">Pan-India replacement guarantee</div>
                </div>
            </div>

            <!-- Center Showroom 3D Stage (Lg: 6 cols) — 100% CLEAN IMAGE SHOWCASE WITH LASER SCAN WIPE -->
            <div class="lg:col-span-6 max-w-[320px] sm:max-w-[350px] lg:max-w-[365px] mx-auto w-full relative stage-3d-wrap reveal reveal-up">
                <!-- Clean Ambient Ground Shadow -->
                <div class="stage-ground-shadow"></div>

                <!-- Clean, Unobstructed Vehicle Poster Showcase (Laser Scan-Wipe Transition) -->
                <div class="relative z-10 rounded-3xl overflow-hidden border border-white/20 shadow-[0_24px_50px_-12px_rgba(0,0,0,0.7)] bg-slate-950/60 backdrop-blur-sm" id="heroShowcaseFrame">
                    <div class="hero-slide-wrap relative">
                        <?php
                        $heroSlides = [
                            ['img' => 'erikshaw-solar.jpeg', 'name' => 'E-Rickshaw Solar',  'range' => '100 KM+',      'tag' => 'Solar Roof',         'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H'],
                            ['img' => 'evelocy-m2.jpeg',     'name' => 'E-Velocy M2',       'range' => '125–150 KM+',  'tag' => 'Dual Disc Brakes',   'battery' => '3.0KW / 60AH Lithium',  'warranty' => '36 Months', 'speed' => '65 KM/H'],
                            ['img' => 'mania-zxi.jpeg',      'name' => 'Electer Mania ZXI', 'range' => '135–150 KM+',  'tag' => 'Sport Scooter',      'battery' => '60V / 60AH Lithium',    'warranty' => '18 Months', 'speed' => '70 KM/H'],
                            ['img' => 'bike-v6.jpeg',        'name' => 'Bike V6 2.0',       'range' => '120 KM+',      'tag' => 'Sports E-Bike',      'battery' => '72V / 60AH Lithium',    'warranty' => '36 Months', 'speed' => '85 KM/H'],
                            ['img' => 'active-chetaak.jpeg', 'name' => 'Active Chetaak',    'range' => '55–125 KM+',   'tag' => 'City Scooter',       'battery' => '60V / 45AH Lithium',    'warranty' => '36 Months', 'speed' => '55 KM/H'],
                            ['img' => 'auto-l3.jpeg',        'name' => 'Auto L-3',          'range' => '90–100 KM+',   'tag' => 'Passenger L-3',      'battery' => '51.2V / 100AH Lithium', 'warranty' => '36 Months', 'speed' => '25 KM/H'],
                        ];
                        foreach ($heroSlides as $i => $s): ?>
                            <img src="assets/ev-products/<?php echo e($s['img']); ?>" alt="<?php echo e($s['name']); ?>"
                                 class="hero-slide<?php echo $i === 0 ? ' active' : ''; ?>"
                                 data-name="<?php echo e($s['name']); ?>"
                                 data-range="<?php echo e($s['range']); ?>"
                                 data-tag="<?php echo e($s['tag']); ?>"
                                 data-battery="<?php echo e($s['battery']); ?>"
                                 data-warranty="<?php echo e($s['warranty']); ?>"
                                 data-speed="<?php echo e($s['speed']); ?>">
                        <?php endforeach; ?>

                        <!-- High-Tech Laser Scanline Beam (Active ONLY during slide change!) -->
                        <div id="laserScanBeam" class="laser-scan-beam hidden"></div>
                    </div>
                </div>

                <!-- Clean Active Model Label Underneath (Completely Outside the Photo) -->
                <div class="text-center mt-3 relative z-10 flex flex-col items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-amber-400 font-mono-num inline-flex items-center gap-1.5 bg-white/10 px-3.5 py-1 rounded-full border border-white/15">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span id="heroSlideName">E-Rickshaw Solar</span> · <span id="heroSlideRange" class="text-white">100 KM+ Range</span>
                    </span>

                    <!-- Mobile Cockpit Live Telemetry (Visible on Mobile/Tablet only) -->
                    <div class="mt-4 w-full grid grid-cols-2 gap-2 lg:hidden text-left">
                        <div class="p-2.5 rounded-2xl bg-white/[0.08] border border-white/15 backdrop-blur-md text-white text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-white/60 uppercase">
                                <i data-lucide="gauge" class="w-3 h-3 text-emerald-400"></i>
                                <span>Range</span>
                            </div>
                            <div class="text-lg font-black text-emerald-400 font-mono-num mt-0.5" id="mobileCockpitRange">100 KM+</div>
                            <div class="text-[9px] text-white/60">Tested on Road</div>
                        </div>

                        <div class="p-2.5 rounded-2xl bg-white/[0.08] border border-white/15 backdrop-blur-md text-white text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-white/60 uppercase">
                                <i data-lucide="battery-charging" class="w-3 h-3 text-amber-400"></i>
                                <span>Battery</span>
                            </div>
                            <div class="text-[11px] font-extrabold text-amber-300 font-mono-num mt-0.5 truncate" id="mobileCockpitBattery">51.2V / 100AH</div>
                            <div class="text-[9px] text-white/60">Smart BMS</div>
                        </div>

                        <div class="p-2.5 rounded-2xl bg-white/[0.08] border border-white/15 backdrop-blur-md text-white text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-white/60 uppercase">
                                <i data-lucide="zap" class="w-3 h-3 text-amber-400"></i>
                                <span>Top Speed</span>
                            </div>
                            <div class="text-lg font-black text-amber-400 font-mono-num mt-0.5" id="mobileCockpitSpeed">25 KM/H</div>
                            <div class="text-[9px] text-white/60">Gov. Regulated</div>
                        </div>

                        <div class="p-2.5 rounded-2xl bg-white/[0.08] border border-white/15 backdrop-blur-md text-white text-center">
                            <div class="flex items-center justify-center gap-1 text-[10px] font-bold text-white/60 uppercase">
                                <i data-lucide="shield-check" class="w-3 h-3 text-sky-400"></i>
                                <span>Warranty</span>
                            </div>
                            <div class="text-lg font-black text-sky-300 font-mono-num mt-0.5" id="mobileCockpitWarranty">36 Months</div>
                            <div class="text-[9px] text-white/60">Pan-India</div>
                        </div>
                    </div>

                    <!-- Mobile Direct WhatsApp CTA -->
                    <div class="mt-2.5 w-full lg:hidden">
                        <a id="heroQuickWhatsappMobile" href="https://wa.me/910000000000?text=Hello%20ELECTRINC,%20I%20want%20to%20inquire%20about%20E-Rickshaw%20Solar" target="_blank" class="w-full py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow-md">
                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                            <span>Inquire Current Model on WhatsApp</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Cockpit Modes & Quick Actions (Lg: 3 cols) -->
            <div class="hidden lg:flex lg:col-span-3 flex-col gap-4 reveal reveal-right delay-75">
                <div class="p-5 rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md text-white">
                    <div class="flex items-center justify-between text-xs font-bold text-white/60 mb-2">
                        <span class="uppercase tracking-wider">Top Speed</span>
                        <i data-lucide="zap" class="w-4 h-4 text-amber-400"></i>
                    </div>
                    <div class="text-3xl font-black text-amber-400 font-mono-num" id="cockpitSpeed">25 KM/H</div>
                    <div class="text-[11px] text-white/60 mt-1">Electronic speed governor</div>
                </div>

                <div class="p-5 rounded-2xl bg-white/[0.07] border border-white/15 backdrop-blur-md text-white">
                    <div class="flex items-center justify-between text-xs font-bold text-white/60 mb-2">
                        <span class="uppercase tracking-wider">Quick Inquiry</span>
                        <i data-lucide="message-circle" class="w-4 h-4 text-emerald-400"></i>
                    </div>
                    <p class="text-xs text-white/70">Connect directly with a verified regional dealer.</p>
                    <a id="heroQuickWhatsapp" href="https://wa.me/910000000000?text=Hello%20ELECTRINC,%20I%20want%20to%20inquire%20about%20E-Rickshaw%20Solar" target="_blank" class="mt-3 w-full py-2.5 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow">
                        <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Chat on WhatsApp
                    </a>
                </div>

                <div class="p-5 rounded-2xl bg-gradient-to-br from-amber-500/20 to-emerald-500/20 border border-white/20 backdrop-blur-md text-white">
                    <span class="text-[10px] uppercase font-bold tracking-wider text-amber-300 block">Franchise Program</span>
                    <p class="text-xs font-bold mt-1">Start Your Own EV Showroom</p>
                    <a href="franchise/index.php" class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold text-emerald-300 hover:text-white transition">
                        Explore Dealership Tiers <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Carousel Switcher Horizontal Dock (Touch Smooth Horizontal Scroll on Mobile) -->
        <div class="mt-6 sm:mt-8 max-w-4xl mx-auto flex items-center justify-start sm:justify-center gap-2 overflow-x-auto no-scrollbar py-2 px-2 flex-nowrap sm:flex-wrap">
            <?php foreach ($heroSlides as $idx => $s): ?>
                <button type="button" 
                        class="hero-thumb-btn shrink-0 flex items-center gap-2 px-3.5 py-1.5 rounded-full transition-all duration-300 border <?php echo $idx === 0 ? 'bg-white/20 border-white/40 text-white shadow-md' : 'bg-white/5 border-white/10 text-white/60 hover:bg-white/15 hover:text-white'; ?>"
                        data-slide="<?php echo $idx; ?>">
                    <span class="w-2 h-2 rounded-full transition-all <?php echo $idx === 0 ? 'bg-amber-400 scale-125' : 'bg-white/30'; ?>"></span>
                    <span class="text-xs font-bold whitespace-nowrap"><?php echo e($s['name']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ INFINITE HIGH-TECH MARQUEE TICKER ============ -->
<div class="bg-[#071710] border-y border-white/10 py-3 overflow-hidden text-white/80 font-mono text-xs uppercase tracking-widest relative z-20">
    <div class="marquee-track flex items-center gap-10 whitespace-nowrap">
        <span>✦ 100% LITHIUM-ION BMS ARCHITECTURE</span>
        <span>✦ ROOFTOP SOLAR RICKSHAWS</span>
        <span>✦ ZERO TAILPIPE EMISSIONS</span>
        <span>✦ 36-MONTH BATTERY GUARANTEE</span>
        <span>✦ PAN-INDIA 150+ FRANCHISE HUBS</span>
        <span>✦ HIGH-TORQUE BLDC DRIVE MOTORS</span>
        <span>✦ 100% LITHIUM-ION BMS ARCHITECTURE</span>
        <span>✦ ROOFTOP SOLAR RICKSHAWS</span>
        <span>✦ ZERO TAILPIPE EMISSIONS</span>
        <span>✦ 36-MONTH BATTERY GUARANTEE</span>
        <span>✦ PAN-INDIA 150+ FRANCHISE HUBS</span>
        <span>✦ HIGH-TORQUE BLDC DRIVE MOTORS</span>
    </div>
</div>

<!-- ============ THE FLEET: AUTOMOTIVE STUDIO SHOWROOM ============ -->
<?php
$catCounts = ['all' => count($products), 'rickshaw' => 0, 'scooter' => 0, 'bike' => 0];
foreach ($products as $pr) {
    $c = $pr['cat'] ?? '';
    if (isset($catCounts[$c])) $catCounts[$c]++;
}
?>
<section id="products" class="max-w-7xl mx-auto px-5 sm:px-6 py-20 sm:py-28">
    <div class="text-center max-w-3xl mx-auto reveal reveal-up">
        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wider text-emerald-900 bg-emerald-100/80 border border-emerald-200/80 shadow-sm">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
            <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-600"></i> Certified Electric Lineup
        </div>
        <h2 class="mt-4 text-3xl sm:text-5xl font-black tracking-tight text-[#0A1F16] leading-tight">
            Precision Engineering.<br>Tailored for <span class="bg-gradient-to-r from-emerald-700 via-green-600 to-amber-600 bg-clip-text text-transparent">Every Journey.</span>
        </h2>
        <p class="mt-3 text-sm sm:text-base text-gray-600 max-w-xl mx-auto leading-relaxed">
            From commercial solar passenger rickshaws to high-performance sports bikes — every model is engineered for long-distance battery endurance, rapid charging, and total rider peace of mind.
        </p>
    </div>

    <!-- 15-Second Smart EV Matcher Toggle & Assistant -->
    <div class="mt-8 flex justify-center reveal reveal-up">
        <button type="button" id="toggleMatcherBtn" onclick="toggleSmartMatcher();" class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full bg-white border border-[#DDD5C5] shadow-sm hover:shadow-md text-[#0A1F16] text-xs sm:text-sm font-extrabold transition-all group hover:border-emerald-600">
            <span class="w-6 h-6 rounded-full bg-gradient-to-br from-amber-400 to-amber-500 text-white flex items-center justify-center shadow-sm">
                <i data-lucide="compass" class="w-3.5 h-3.5 group-hover:rotate-45 transition-transform duration-300"></i>
            </span>
            <span>Not sure which EV to choose? <strong class="text-emerald-800">Find Your Match in 15 Sec</strong></span>
            <i data-lucide="chevron-down" id="matcherChevron" class="w-4 h-4 text-gray-500 transition-transform duration-300"></i>
        </button>
    </div>

    <!-- Matcher Interactive Panel (Foldable) -->
    <div id="smartMatcherPanel" class="hidden mt-6 max-w-3xl mx-auto p-6 rounded-3xl bg-gradient-to-br from-white via-[#FAF8F4] to-[#F5EFE6] border border-[#DDD5C5] shadow-xl transition-all duration-300">
        <div class="flex items-center justify-between border-b border-gray-200/80 pb-4 mb-5">
            <div>
                <h4 class="text-base font-black text-[#0A1F16] flex items-center gap-2">
                    <i data-lucide="sparkles" class="w-4 h-4 text-amber-500"></i> Smart EV Recommendation Assistant
                </h4>
                <p class="text-xs text-gray-500">Select your ride purpose and daily run to spotlight the best-fit models instantly.</p>
            </div>
            <button type="button" onclick="resetSmartMatcher();" class="text-[11px] font-bold text-gray-500 hover:text-emerald-700 underline">Reset</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Step 1: Ride Purpose -->
            <div>
                <label class="text-xs font-black uppercase tracking-wider text-gray-600 block mb-2">1. Your Primary Purpose</label>
                <div class="grid grid-cols-2 gap-2" id="matcherPurposeGroup">
                    <button type="button" class="matcher-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-left hover:border-emerald-600 transition flex items-center gap-2" data-purpose="commute" onclick="selectMatcherPurpose(this, 'commute');">
                        <span>🏢 Daily Commute</span>
                    </button>
                    <button type="button" class="matcher-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-left hover:border-emerald-600 transition flex items-center gap-2" data-purpose="passenger" onclick="selectMatcherPurpose(this, 'passenger');">
                        <span>🛺 Passenger Auto</span>
                    </button>
                    <button type="button" class="matcher-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-left hover:border-emerald-600 transition flex items-center gap-2" data-purpose="cargo" onclick="selectMatcherPurpose(this, 'cargo');">
                        <span>📦 Cargo Delivery</span>
                    </button>
                    <button type="button" class="matcher-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-left hover:border-emerald-600 transition flex items-center gap-2" data-purpose="sport" onclick="selectMatcherPurpose(this, 'sport');">
                        <span>🏍️ Sports Performance</span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Daily Mileage -->
            <div>
                <label class="text-xs font-black uppercase tracking-wider text-gray-600 block mb-2">2. Daily Distance Target</label>
                <div class="grid grid-cols-3 gap-2" id="matcherRangeGroup">
                    <button type="button" class="matcher-range-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-center hover:border-emerald-600 transition" data-range="low" onclick="selectMatcherRange(this, 'low');">
                        <span>&lt; 60 KM</span>
                    </button>
                    <button type="button" class="matcher-range-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-center hover:border-emerald-600 transition" data-range="mid" onclick="selectMatcherRange(this, 'mid');">
                        <span>60–100 KM</span>
                    </button>
                    <button type="button" class="matcher-range-btn p-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-center hover:border-emerald-600 transition" data-range="high" onclick="selectMatcherRange(this, 'high');">
                        <span>120+ KM</span>
                    </button>
                </div>
                <div id="matcherResultBadge" class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 font-bold hidden flex items-center justify-between">
                    <span id="matcherResultText">🎯 Matches highlighted in fleet below!</span>
                    <a href="#productGrid" class="text-[11px] underline font-extrabold text-emerald-900">View Matches ↓</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Luxury Segmented Filter Capsule Dock & Instant Live Model Search -->
    <div class="mt-8 flex flex-col items-center gap-4 reveal reveal-up w-full">
        <!-- Category Filter Pills (Touch-Smooth Horizontal Scroll Container - Zero Left Cut-Off) -->
        <div class="w-full overflow-x-auto no-scrollbar py-2 px-3 sm:px-4 flex justify-start lg:justify-center">
            <div class="inline-flex items-center gap-1.5 p-1.5 sm:p-2 rounded-full bg-[#EBE5D8]/90 backdrop-blur-md border border-[#DDD5C5] shadow-inner shrink-0" id="filterBar">
                <button type="button" class="filter-pill active shrink-0 whitespace-nowrap px-4 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm font-bold flex items-center gap-2" data-filter="all">
                    <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                    <span>All Vehicles</span>
                    <span class="text-[10px] py-0.5 px-2 rounded-full bg-white/20 font-mono font-bold"><?php echo $catCounts['all']; ?></span>
                </button>
                <button type="button" class="filter-pill shrink-0 whitespace-nowrap px-4 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm font-bold flex items-center gap-2" data-filter="scooter">
                    <span>🛵 E-Scooter</span>
                    <span class="text-[10px] py-0.5 px-2 rounded-full bg-black/5 font-mono font-bold"><?php echo $catCounts['scooter']; ?></span>
                </button>
                <button type="button" class="filter-pill shrink-0 whitespace-nowrap px-4 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm font-bold flex items-center gap-2" data-filter="rickshaw">
                    <span>🛺 E-Rickshaw & Loader</span>
                    <span class="text-[10px] py-0.5 px-2 rounded-full bg-black/5 font-mono font-bold"><?php echo $catCounts['rickshaw']; ?></span>
                </button>
                <button type="button" class="filter-pill shrink-0 whitespace-nowrap px-4 sm:px-5 py-2 sm:py-2.5 rounded-full text-xs sm:text-sm font-bold flex items-center gap-2" data-filter="bike">
                    <span>🏍️ Sports E-Bike</span>
                    <span class="text-[10px] py-0.5 px-2 rounded-full bg-black/5 font-mono font-bold"><?php echo $catCounts['bike']; ?></span>
                </button>
            </div>
        </div>

        <!-- Instant Vehicle Search Input -->
        <div class="w-full max-w-sm px-1 relative">
            <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
            <input type="text" id="quickSearchFleet" placeholder="Quick search model (e.g. Mania, Solar, Chetaak)..." class="w-full pl-11 pr-4 py-2.5 rounded-full bg-white border border-[#DDD5C5] text-xs sm:text-sm font-semibold text-gray-800 placeholder-gray-400 shadow-xs focus:outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-500/20 transition">
        </div>
    </div>

    <!-- ============ 2-COLUMN WIDESCREEN LUXURY STUDIO FLEET SHOWCASE ============ -->
    <div class="mt-8 sm:mt-10 grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8" id="productGrid">
        <?php foreach ($products as $idx => $p): ?>
            <div class="studio-card studio-card-3d group reveal reveal-up rounded-[28px] sm:rounded-[30px] bg-white border border-[#E7E2D6] p-4 sm:p-6 shadow-[0_12px_36px_-8px_rgba(10,31,22,0.06)] hover:shadow-[0_24px_60px_-10px_rgba(10,31,22,0.18)] hover:border-emerald-400/60 transition-all duration-300 relative overflow-hidden flex flex-col justify-between"
                 data-cat="<?php echo e($p['cat']); ?>"
                 data-model-name="<?php echo strtolower(e($p['name'])); ?>"
                 data-range-val="<?php echo (int)preg_replace('/[^0-9]/', '', explode('–', $p['range'])[0] ?? '50'); ?>">
                
                <!-- 3D Glare Reflection Overlay -->
                <div class="card-glare-overlay"></div>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 sm:gap-6 items-center">
                    
                    <!-- Left: Studio Stage Pedestal (sm: 5 cols, mobile: widescreen 210px) -->
                    <div class="sm:col-span-5 relative rounded-2xl overflow-hidden bg-gradient-to-b from-[#F8F6F0] via-[#F2ECE1] to-[#EAE4D7] border border-[#E2DDD0] p-4 flex items-center justify-center group/stage shadow-inner h-[210px] sm:h-auto sm:aspect-[3/3.9]">
                        
                        <!-- Category Tag Pill (Top Left Only - NO PRICE ON TOP!) -->
                        <div class="absolute top-3 left-3 z-20 flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#0A1F16]/90 backdrop-blur-md text-white text-[10px] font-extrabold uppercase tracking-wider border border-white/20 shadow-md">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span><?php echo e($catLabels[$p['cat']] ?? 'EV'); ?></span>
                        </div>

                        <!-- Soft Ground Contact Shadow -->
                        <div class="absolute bottom-3 inset-x-4 h-5 bg-black/15 rounded-full blur-md pointer-events-none"></div>

                        <!-- High-Tech Laser Cyber-Sweep Beam on Card Hover -->
                        <div class="card-laser-beam"></div>

                        <!-- Vehicle Flyer Artwork with Smooth Zoom -->
                        <img src="assets/ev-products/<?php echo e($p['img']); ?>" 
                             alt="<?php echo e($p['name']); ?>" 
                             class="max-h-full max-w-full w-auto h-auto object-contain zoom-img drop-shadow-md relative z-10 transition-transform duration-500 group-hover:scale-105">

                        <!-- Click Stage to Quick View Prompt (Always accessible on touch) -->
                        <button type="button" 
                                class="spec-modal-trigger absolute inset-0 z-20 opacity-80 sm:opacity-0 sm:group-hover/stage:opacity-100 transition-opacity bg-black/10 sm:bg-black/15 backdrop-blur-[1px] flex items-end justify-center pb-2.5 sm:pb-3 text-[11px] font-extrabold text-white"
                                data-name="<?php echo e($p['name']); ?>"
                                data-img="<?php echo e($p['img']); ?>"
                                data-cat="<?php echo e($catLabels[$p['cat']]); ?>"
                                data-tag="<?php echo e($p['tag']); ?>"
                                data-range="<?php echo e($p['range']); ?>"
                                data-battery="<?php echo e($p['battery']); ?>"
                                data-warranty="<?php echo e($p['warranty']); ?>"
                                data-speed="<?php echo e($p['speed'] ?? '50 KM/H'); ?>"
                                data-charge="<?php echo e($p['charge'] ?? '3–4 Hrs'); ?>">
                            <span class="px-3 py-1 rounded-full bg-[#0A1F16]/90 backdrop-blur-md border border-white/20 shadow flex items-center gap-1.5">
                                <i data-lucide="eye" class="w-3.5 h-3.5 text-emerald-400"></i> Inspect Specs
                            </span>
                        </button>
                    </div>

                    <!-- Right: Cockpit Specifications & Actions (sm: 7 cols) -->
                    <div class="sm:col-span-7 flex flex-col justify-between h-full">
                        <div>
                            <!-- Top Status & Compare Toggle -->
                            <div class="flex items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide bg-amber-50 text-amber-900 border border-amber-200/80">
                                    <i data-lucide="sparkles" class="w-3 h-3 text-amber-500"></i>
                                    <span><?php echo e($p['tag']); ?></span>
                                </span>

                                <button type="button" 
                                        class="compare-toggle-btn shrink-0 text-[10px] font-extrabold px-2.5 py-1 rounded-full border border-gray-200 bg-white/95 hover:bg-emerald-50 text-gray-700 flex items-center gap-1 transition shadow-xs"
                                        data-name="<?php echo e($p['name']); ?>"
                                        data-img="<?php echo e($p['img']); ?>"
                                        data-cat="<?php echo e($catLabels[$p['cat']]); ?>"
                                        data-tag="<?php echo e($p['tag']); ?>"
                                        data-range="<?php echo e($p['range']); ?>"
                                        data-battery="<?php echo e($p['battery']); ?>"
                                        data-warranty="<?php echo e($p['warranty']); ?>"
                                        data-speed="<?php echo e($p['speed'] ?? '50 KM/H'); ?>"
                                        data-charge="<?php echo e($p['charge'] ?? '3–4 Hrs'); ?>"
                                        title="Add to Side-by-Side Comparison">
                                    <i data-lucide="arrow-left-right" class="w-3 h-3 text-emerald-600"></i>
                                    <span class="compare-btn-label">Compare</span>
                                </button>
                            </div>

                            <!-- Model Title -->
                            <h3 class="text-lg sm:text-2xl font-black text-[#0A1F16] tracking-tight group-hover:text-emerald-700 transition-colors mt-2">
                                <?php echo e($p['name']); ?>
                            </h3>

                            <!-- Telemetry Spec Stack (3 Clean Rows) -->
                            <div class="mt-3.5 sm:mt-4 space-y-2">
                                <!-- Range -->
                                <div class="flex items-center justify-between px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl bg-gradient-to-r from-emerald-50/70 to-white border border-emerald-200/80">
                                    <span class="text-[10px] sm:text-[11px] font-bold text-gray-600 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="gauge" class="w-3.5 h-3.5 text-emerald-600 shrink-0"></i>
                                        <span>Certified Range</span>
                                    </span>
                                    <span class="text-xs sm:text-sm font-black text-emerald-700 font-mono-num"><?php echo e($p['range']); ?></span>
                                </div>

                                <!-- Battery Spec -->
                                <div class="flex items-center justify-between px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl bg-gradient-to-r from-amber-50/60 to-white border border-amber-200/80">
                                    <span class="text-[10px] sm:text-[11px] font-bold text-gray-600 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="battery-charging" class="w-3.5 h-3.5 text-amber-600 shrink-0"></i>
                                        <span>Battery Pack</span>
                                    </span>
                                    <span class="text-[10px] sm:text-xs font-bold text-gray-900 font-mono truncate max-w-[130px] sm:max-w-[140px] text-right" title="<?php echo e($p['battery']); ?>"><?php echo e($p['battery']); ?></span>
                                </div>

                                <!-- Warranty Shield -->
                                <div class="flex items-center justify-between px-3 py-2 sm:px-3.5 sm:py-2.5 rounded-xl bg-gradient-to-r from-sky-50/60 to-white border border-sky-200/80">
                                    <span class="text-[10px] sm:text-[11px] font-bold text-gray-600 flex items-center gap-1.5 sm:gap-2">
                                        <i data-lucide="shield-check" class="w-3.5 h-3.5 text-sky-600 shrink-0"></i>
                                        <span>Warranty</span>
                                    </span>
                                    <span class="text-xs sm:text-sm font-black text-sky-700 font-mono-num"><?php echo e($p['warranty']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Dock -->
                        <div class="mt-4 sm:mt-5 pt-3 border-t border-[#EBE5D8] flex items-center gap-2 sm:gap-2.5">
                            <button type="button" 
                                    class="spec-modal-trigger flex-1 min-h-[42px] py-2.5 px-3 sm:px-4 rounded-full text-xs font-extrabold text-white btn-brand-primary flex items-center justify-center gap-2 group/btn shadow-md"
                                    data-name="<?php echo e($p['name']); ?>"
                                    data-img="<?php echo e($p['img']); ?>"
                                    data-cat="<?php echo e($catLabels[$p['cat']]); ?>"
                                    data-tag="<?php echo e($p['tag']); ?>"
                                    data-range="<?php echo e($p['range']); ?>"
                                    data-battery="<?php echo e($p['battery']); ?>"
                                    data-warranty="<?php echo e($p['warranty']); ?>"
                                    data-speed="<?php echo e($p['speed'] ?? '50 KM/H'); ?>"
                                    data-charge="<?php echo e($p['charge'] ?? '3–4 Hrs'); ?>">
                                <span>Quick Spec & Enquire</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover/btn:translate-x-1"></i>
                            </button>

                            <a href="https://wa.me/910000000000?text=<?php echo urlencode('Hello ELECTRINC, I want to inquire about ' . $p['name'] . ' (' . $p['tag'] . ').'); ?>" 
                               target="_blank" 
                               class="w-10 h-10 shrink-0 rounded-full border border-emerald-300/80 bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 flex items-center justify-center transition-all duration-200 shadow-xs hover:scale-105" 
                               title="Inquire on WhatsApp">
                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ REAL CUSTOMER VERIFIED BRAND PROMOTION FEED ============ -->
<?php if (!empty($feedVideos)): ?>
<section id="customer-feed" class="py-20 bg-[#FAF8F4] border-y border-[#EBE5D8]">
    <div class="max-w-7xl mx-auto px-5 sm:px-6">
        <div class="text-center max-w-2xl mx-auto reveal reveal-up">
            <span class="text-xs font-bold uppercase tracking-wider text-[#E0700C]">Real Riders · Real Proof</span>
            <h2 class="mt-2 text-3xl font-black text-[#0A1F16]">Live Verified Brand Promotion Feed</h2>
            <p class="mt-2 text-xs sm:text-sm text-gray-600">Verified riders enjoying their Free Electric Scooters with verified live GPS timestamps across India.</p>
        </div>

        <div class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <?php foreach ($feedVideos as $i => $fv): ?>
                <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm flex flex-col justify-between reveal reveal-up">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                <i data-lucide="shield-check" class="w-3 h-3 text-green-600"></i> Verified
                            </span>
                            <span class="text-[10px] font-mono text-gray-500">
                                <?php echo date('M Y', strtotime($fv['created_at'])); ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-sm text-gray-900"><?php echo e($fv['full_name']); ?></h4>
                        <p class="text-xs text-[#E0700C] font-semibold mt-0.5"><?php echo e($fv['vehicle_model']); ?></p>
                        <p class="text-[11px] text-gray-500 font-mono"><?php echo e($fv['registration_number']); ?></p>

                        <div class="mt-3 pt-3 border-t border-dashed border-gray-200 text-[11px] space-y-1 text-gray-600">
                            <div class="flex items-center gap-1.5 font-mono">
                                <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                                <i data-lucide="map-pin" class="w-3 h-3 text-red-500 shrink-0"></i>
                                <span>GPS: <?php echo number_format($fv['gps_latitude'], 4); ?>, <?php echo number_format($fv['gps_longitude'], 4); ?></span>
                            </div>
                            <div class="text-[10px] text-green-700 font-semibold flex items-center gap-1">
                                <i data-lucide="check" class="w-3 h-3"></i> Monthly BPR Active
                            </div>
                        </div>
                    </div>

                    <?php if ((int)$fv['video_deleted'] === 0 && !empty($fv['video_file_path'])): ?>
                        <div class="mt-4">
                            <a href="<?php echo e($fv['video_file_path']); ?>" target="_blank" class="w-full py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                                <i data-lucide="play-circle" class="w-3.5 h-3.5"></i> Watch Rider Video
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ ENGINEERING & TRUST: APPLE-STYLE BENTO GRID ============ -->
<section id="why-us" class="py-20 sm:py-28 bg-[#FAF8F4] border-b border-[#EBE5D8] relative overflow-hidden">
    <!-- Ambient Engineering Background Dot Grid -->
    <div class="absolute inset-0 opacity-40 pointer-events-none" style="background-image: radial-gradient(#DDD6C8 1px, transparent 1px); background-size: 32px 32px;"></div>

    <div class="max-w-7xl mx-auto px-5 sm:px-6 relative z-10">
        <div class="text-center max-w-2xl mx-auto reveal reveal-up">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wider text-emerald-900 bg-emerald-100/80 border border-emerald-200/80 shadow-xs">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                <i data-lucide="cpu" class="w-3.5 h-3.5 text-amber-600"></i> The Technology Behind
            </div>
            <h2 class="mt-4 text-3xl sm:text-5xl font-black text-[#0A1F16] tracking-tight leading-tight">
                Engineered for Reliability.<br><span class="bg-gradient-to-r from-emerald-700 via-green-600 to-amber-600 bg-clip-text text-transparent">Built for Business.</span>
            </h2>
            <p class="mt-3 text-sm sm:text-base text-gray-600 leading-relaxed">
                How ELECTRINC combines cutting-edge clean tech with a profitable, transparent franchise ecosystem.
            </p>
        </div>

        <div class="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Bento 1: Flagship Lithium-Ion BMS Battery Architecture (2 Cols) -->
            <div class="bento-card studio-card-3d md:col-span-2 p-5 sm:p-8 md:p-10 flex flex-col justify-between bg-gradient-to-br from-[#06140D] via-[#0A1F16] to-[#0D291B] text-white border border-white/15 shadow-[0_20px_50px_-10px_rgba(10,31,22,0.35)] reveal reveal-up relative overflow-hidden group">
                <!-- Ambient Volumetric Orbs -->
                <div class="absolute -top-24 -right-24 w-80 h-80 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    <!-- Text & Spec Info (Lg: 7 cols) -->
                    <div class="lg:col-span-7">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="w-11 h-11 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 shadow-[0_0_20px_rgba(16,185,129,0.25)]">
                                <i data-lucide="battery-charging" class="w-5 h-5 animate-pulse"></i>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-mono font-bold tracking-wider text-emerald-300 bg-emerald-950/80 border border-emerald-500/40">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                                <span>SMART BMS 4.0 ACTIVE</span>
                            </span>
                        </div>
                        <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                            Next-Gen Lithium-Ion BMS Battery Architecture
                        </h3>
                        <p class="mt-3 text-xs sm:text-sm text-white/80 leading-relaxed">
                            Say goodbye to sluggish lead-acid batteries. Every ELECTRINC vehicle is powered by high-density lithium-ion chemistry with a Smart Battery Management System (BMS) for thermal regulation, rapid charging, and zero maintenance.
                        </p>
                    </div>

                    <!-- Animated BMS Battery Cell Visualizer (Lg: 5 cols) -->
                    <div class="lg:col-span-5 flex flex-col items-center justify-center">
                        <div class="w-full max-w-[280px] p-4 rounded-2xl bg-white/[0.06] border border-white/15 backdrop-blur-md shadow-2xl">
                            <!-- Battery Header -->
                            <div class="flex items-center justify-between text-[11px] font-mono text-white/70 mb-3 border-b border-white/10 pb-2">
                                <span class="flex items-center gap-1.5"><i data-lucide="activity" class="w-3.5 h-3.5 text-emerald-400"></i> Cell Balancer</span>
                                <span class="text-emerald-400 font-bold">99.8% Healthy</span>
                            </div>
                            <!-- 4 Animated Prismatic Cells -->
                            <div class="grid grid-cols-4 gap-2 h-20 items-end p-2 bg-black/40 rounded-xl border border-white/10">
                                <div class="relative w-full h-full bg-white/10 rounded-md overflow-hidden flex items-end">
                                    <div class="w-full rounded-md bg-gradient-to-t from-emerald-600 to-emerald-400" style="animation: cellChargeWave 2.8s ease-in-out infinite;"></div>
                                </div>
                                <div class="relative w-full h-full bg-white/10 rounded-md overflow-hidden flex items-end">
                                    <div class="w-full rounded-md bg-gradient-to-t from-emerald-600 to-emerald-400" style="animation: cellChargeWave 2.8s ease-in-out infinite 0.3s;"></div>
                                </div>
                                <div class="relative w-full h-full bg-white/10 rounded-md overflow-hidden flex items-end">
                                    <div class="w-full rounded-md bg-gradient-to-t from-emerald-600 to-emerald-400" style="animation: cellChargeWave 2.8s ease-in-out infinite 0.6s;"></div>
                                </div>
                                <div class="relative w-full h-full bg-white/10 rounded-md overflow-hidden flex items-end">
                                    <div class="w-full rounded-md bg-gradient-to-t from-emerald-600 to-emerald-400" style="animation: cellChargeWave 2.8s ease-in-out infinite 0.9s;"></div>
                                </div>
                            </div>
                            <!-- Telemetry Row -->
                            <div class="mt-3 flex items-center justify-between text-[10px] font-mono text-white/60">
                                <span>Temp: <strong class="text-amber-300">28°C</strong></span>
                                <span>Charge: <strong class="text-emerald-300">Rapid 1.5C</strong></span>
                                <span>Volt: <strong class="text-sky-300">51.2V</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Micro Spec Telemetry Footer -->
                <div class="mt-8 pt-6 border-t border-white/10 grid grid-cols-3 gap-2 sm:gap-4 text-center relative z-10">
                    <div class="p-2 sm:p-3 rounded-2xl bg-white/[0.04] border border-white/10">
                        <span class="text-xl sm:text-3xl font-black text-amber-400 font-mono-num block">2.5X</span>
                        <span class="text-[10px] sm:text-[11px] font-bold text-white/70 block mt-0.5">Lifespan vs Lead</span>
                    </div>
                    <div class="p-2 sm:p-3 rounded-2xl bg-white/[0.04] border border-white/10">
                        <span class="text-xl sm:text-3xl font-black text-emerald-300 font-mono-num block">3–4 Hrs</span>
                        <span class="text-[10px] sm:text-[11px] font-bold text-white/70 block mt-0.5">Rapid Charge</span>
                    </div>
                    <div class="p-2 sm:p-3 rounded-2xl bg-white/[0.04] border border-white/10">
                        <span class="text-xl sm:text-3xl font-black text-sky-300 font-mono-num block">36 Mos</span>
                        <span class="text-[10px] sm:text-[11px] font-bold text-white/70 block mt-0.5">Lithium Warranty</span>
                    </div>
                </div>
            </div>

            <!-- Bento 2: Solar Rooftop Innovation -->
            <div class="bento-card studio-card-3d p-5 sm:p-8 flex flex-col justify-between bg-gradient-to-br from-amber-50/70 via-white to-white border border-amber-200/80 reveal reveal-up delay-75 group">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div class="relative w-12 h-12">
                            <span class="absolute inset-0 rounded-2xl bg-amber-400/30 blur-md group-hover:scale-125 transition-transform"></span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-white flex items-center justify-center shadow-md relative z-10">
                                <i data-lucide="sun" class="w-6 h-6 group-hover:rotate-90 transition-transform duration-700"></i>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 border border-amber-300 font-mono">
                            ⚡ SOLAR HYBRID
                        </span>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-black text-[#0A1F16] tracking-tight">Solar-Ready Variants</h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600 leading-relaxed">
                        Our flagship E-Rickshaws and E-Loaders come equipped with monocrystalline rooftop solar panels that continuously charge while parked or in transit, dropping grid charging costs to near zero.
                    </p>

                    <!-- Dynamic Solar Metric Box -->
                    <div class="mt-5 p-3 rounded-2xl bg-gradient-to-r from-amber-100/60 via-amber-50 to-white border border-amber-200/80 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                            <span class="text-xs font-black text-amber-900 font-mono">Monocrystalline 400W</span>
                        </div>
                        <span class="text-xs font-black text-amber-700 font-mono-num">+15–20 KM/Day</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-amber-100 flex items-center gap-2 text-xs font-bold text-amber-800">
                    <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
                    <span>Free Sunlight Miles Every Single Day</span>
                </div>
            </div>

            <!-- Bento 3: Up to 36-Month Warranty Shield -->
            <div class="bento-card studio-card-3d p-5 sm:p-8 flex flex-col justify-between bg-gradient-to-br from-sky-50/70 via-white to-white border border-sky-200/80 reveal reveal-up group">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div class="relative w-12 h-12">
                            <span class="absolute inset-0 rounded-2xl bg-sky-400/30 blur-md group-hover:scale-125 transition-transform"></span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 text-white flex items-center justify-center shadow-md relative z-10">
                                <i data-lucide="shield-check" class="w-6 h-6"></i>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-sky-100 text-sky-900 border border-sky-300 font-mono">
                            🛡️ CERTIFIED SHIELD
                        </span>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-black text-[#0A1F16] tracking-tight">Up to 36-Month Warranty</h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600 leading-relaxed">
                        Industry-leading comprehensive battery and vehicle warranty on our flagship range, giving both fleet operators and individual riders total peace of mind.
                    </p>

                    <!-- Specular Warranty Checklist Chips -->
                    <div class="mt-5 space-y-2 text-xs font-bold text-gray-700">
                        <div class="flex items-center gap-2 p-2 rounded-xl bg-sky-50/60 border border-sky-200/60">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-sky-600"></i>
                            <span>100% Replacement Guarantee on BMS Faults</span>
                        </div>
                        <div class="flex items-center gap-2 p-2 rounded-xl bg-sky-50/60 border border-sky-200/60">
                            <i data-lucide="check" class="w-3.5 h-3.5 text-sky-600"></i>
                            <span>Zero Hidden Clauses · Free Diagnostics</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-sky-100 flex items-center gap-1.5 text-xs font-bold text-sky-800">
                    <i data-lucide="check-circle" class="w-4 h-4 text-sky-600"></i>
                    <span>Hassle-Free Regional Claim Centers</span>
                </div>
            </div>

            <!-- Bento 4: Pan-India Franchise Network -->
            <div class="bento-card studio-card-3d p-5 sm:p-8 flex flex-col justify-between bg-gradient-to-br from-emerald-50/70 via-white to-white border border-emerald-200/80 reveal reveal-up delay-75 group">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div class="relative w-12 h-12">
                            <span class="absolute inset-0 rounded-2xl bg-emerald-500/30 animate-ping"></span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-600 to-green-700 text-white flex items-center justify-center shadow-md relative z-10">
                                <i data-lucide="map-pin" class="w-6 h-6"></i>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300 font-mono">
                            📍 22 STATES ACTIVE
                        </span>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-black text-[#0A1F16] tracking-tight">Pan-India Franchise Network</h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600 leading-relaxed">
                        A rapidly expanding network of State Super-Stockists, City Master Showrooms, and Mobile Service Units bringing sales, spares, and doorstep maintenance closer to you.
                    </p>

                    <!-- Live Network Radar Simulation -->
                    <div class="mt-5 p-3.5 rounded-2xl bg-emerald-950 text-white border border-emerald-800 relative overflow-hidden flex items-center justify-between">
                        <div class="flex items-center gap-2.5 relative z-10">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-mono font-bold text-emerald-300">Live Dealership Grid</span>
                        </div>
                        <span class="text-xs font-black text-amber-400 font-mono-num relative z-10">150+ Hubs</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-emerald-100 flex items-center gap-1.5 text-xs font-bold text-emerald-800">
                    <i data-lucide="store" class="w-4 h-4 text-emerald-600"></i>
                    <span>Doorstep Spares & Rapid Technician Support</span>
                </div>
            </div>

            <!-- Bento 5: Franchise Business Model -->
            <div class="bento-card studio-card-3d p-5 sm:p-8 flex flex-col justify-between bg-gradient-to-br from-purple-50/70 via-white to-white border border-purple-200/80 reveal reveal-up delay-150 group">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div class="relative w-12 h-12">
                            <span class="absolute inset-0 rounded-2xl bg-purple-400/30 blur-md group-hover:scale-125 transition-transform"></span>
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-purple-600 to-indigo-700 text-white flex items-center justify-center shadow-md relative z-10">
                                <i data-lucide="indian-rupee" class="w-6 h-6"></i>
                            </div>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-purple-100 text-purple-900 border border-purple-300 font-mono">
                            💰 DUAL-WALLET
                        </span>
                    </div>

                    <h3 class="text-xl sm:text-2xl font-black text-[#0A1F16] tracking-tight">Franchise Income Architecture</h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600 leading-relaxed">
                        Automated turnover tracking, retail vehicle margins, and 10-level sponsor compensation plans built directly into our secure cloud franchise portal.
                    </p>

                    <!-- Upward Momentum Sparkline Box -->
                    <div class="mt-5 p-3 rounded-2xl bg-gradient-to-r from-purple-100/70 to-indigo-50 border border-purple-200 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-purple-700"></i>
                            <span class="text-xs font-bold text-purple-950 font-mono">10-Tier Cloud Split</span>
                        </div>
                        <span class="text-xs font-black text-purple-700 font-mono">Real-Time Payout</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-purple-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-purple-800 flex items-center gap-1.5">
                        <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i> Instant Dual-Wallet Credit
                    </span>
                    <a href="franchise/index.php" class="text-[11px] font-black text-purple-700 hover:text-purple-900 underline flex items-center gap-1">
                        Apply <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FRANCHISE CTA: LUXURY SHOWROOM INVITATION ============ -->
<section id="franchise" class="py-20 sm:py-28 relative overflow-hidden bg-gradient-to-b from-[#06140D] via-[#0A1F16] to-[#040D08] text-white">
    <div class="aurora-glow-1"></div>
    <div class="aurora-glow-2"></div>
    
    <div class="relative z-10 max-w-7xl mx-auto px-5 sm:px-6">
        <!-- Top Section Statement -->
        <div class="text-center max-w-3xl mx-auto reveal reveal-up">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider text-amber-300 bg-amber-500/15 border border-amber-500/30 backdrop-blur-md shadow-sm">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                <i data-lucide="handshake" class="w-4 h-4 text-amber-400"></i> Pan-India Dealership Program
            </div>
            <h2 class="mt-5 text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
                Own an ELECTRINC Showroom.<br>
                <span class="bg-gradient-to-r from-amber-400 via-emerald-300 to-green-400 bg-clip-text text-transparent">Lead India's Clean EV Revolution.</span>
            </h2>
            <p class="mt-4 text-sm sm:text-base text-white/80 max-w-xl mx-auto leading-relaxed">
                State, City, and Mobile franchise tiers available with turnkey showroom design, cloud ERP billing, certified technician training, and pre-allocated customer leads.
            </p>
        </div>

        <!-- 3 Interactive Dealership Tier Cards (3D Magnetic Tilt) -->
        <div class="mt-14 grid grid-cols-1 md:grid-cols-3 gap-6 sm:gap-8">
            
            <!-- Tier 1: City Master Showroom -->
            <div class="studio-card-3d rounded-[28px] bg-[#0A2318] border border-amber-500/30 p-5 sm:p-8 flex flex-col justify-between hover:border-amber-400 hover:shadow-[0_20px_50px_rgba(224,112,12,0.25)] transition-all duration-300 group shadow-xl relative overflow-hidden text-white">
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-amber-400 shadow-md">
                            <i data-lucide="store" class="w-6 h-6"></i>
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 font-mono">
                            ★ FLAGSHIP TIER
                        </span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-white">City Master Showroom</h3>
                    <p class="text-xs text-amber-300 font-extrabold mt-1">1,500 – 3,000 Sq. Ft. Prime Display</p>
                    <p class="mt-3 text-xs sm:text-sm text-gray-200 leading-relaxed font-normal">
                        Dedicated 2-bay EV service workshop, luxury customer lounge, and direct exclusive city retail distribution rights.
                    </p>

                    <div class="mt-6 space-y-3 text-xs text-white">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Maximum Vehicle & Spare Margins</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Turnkey 3D Architectural Showroom Kit</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Pre-allocated Verified Customer Inquiries</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-5 border-t border-white/10 relative z-10">
                    <a href="franchise/index.php" class="w-full py-3 rounded-full bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-lg transition hover:scale-[1.02]">
                        <span>Apply For City Showroom</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            <!-- Tier 2: Super-Stockist Hub (The Middle Card - High Contrast Deep Luxury Green) -->
            <div class="studio-card-3d rounded-[28px] bg-[#0C2B1D] border-2 border-emerald-500/80 p-5 sm:p-8 flex flex-col justify-between hover:border-emerald-400 hover:shadow-[0_25px_60px_rgba(16,185,129,0.3)] transition-all duration-300 group shadow-2xl relative overflow-hidden text-white">
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-emerald-500/20 rounded-full blur-2xl pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 shadow-md">
                            <i data-lucide="truck" class="w-6 h-6"></i>
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full bg-emerald-500 text-white shadow-md font-mono flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"></span>
                            <span>★ HIGH VOLUME OVERRIDE</span>
                        </span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-white">Super-Stockist Depot</h3>
                    <p class="text-xs text-emerald-300 font-extrabold mt-1">State & District Warehousing</p>
                    <p class="mt-3 text-xs sm:text-sm text-gray-200 leading-relaxed font-normal">
                        Regional logistics hub supplying ready vehicle stocks, lithium battery replacements, and certified spare parts.
                    </p>

                    <div class="mt-6 space-y-3 text-xs text-white">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">District-wide Volume Turnover Rebates</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Priority Factory Vehicle Allocations</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Automated Cloud Inventory ERP Integration</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-5 border-t border-white/10 relative z-10">
                    <a href="franchise/index.php" class="w-full py-3 rounded-full bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-lg transition hover:scale-[1.02]">
                        <span>Apply For Super-Stockist</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>

            <!-- Tier 3: Town Dealership & Service Point -->
            <div class="studio-card-3d rounded-[28px] bg-[#0A2318] border border-sky-500/30 p-5 sm:p-8 flex flex-col justify-between hover:border-sky-400 hover:shadow-[0_20px_50px_rgba(14,165,233,0.25)] transition-all duration-300 group shadow-xl relative overflow-hidden text-white">
                <div class="absolute -top-12 -right-12 w-40 h-40 bg-sky-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <span class="w-12 h-12 rounded-2xl bg-sky-500/20 border border-sky-500/40 flex items-center justify-center text-sky-400 shadow-md">
                            <i data-lucide="map-pin" class="w-6 h-6"></i>
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-full bg-sky-500/20 text-sky-300 border border-sky-500/40 font-mono">
                            🚀 LOW CAPEX
                        </span>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-white">Town Dealership Point</h3>
                    <p class="text-xs text-sky-300 font-extrabold mt-1">500 – 1,000 Sq. Ft. Fast Setup</p>
                    <p class="mt-3 text-xs sm:text-sm text-gray-200 leading-relaxed font-normal">
                        Ideal for high-density towns and semi-urban clusters. Rapid breakeven model with complete marketing collateral.
                    </p>

                    <div class="mt-6 space-y-3 text-xs text-white">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Fast Breakeven within 6–8 Months</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Free Master Technician Training</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                            <span class="text-white font-medium">Instant Dual-Wallet Payout Engine</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 pt-5 border-t border-white/10 relative z-10">
                    <a href="franchise/index.php" class="w-full py-3 rounded-full bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-extrabold text-xs flex items-center justify-center gap-2 shadow-lg transition hover:scale-[1.02]">
                        <span>Apply For Dealership</span>
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Franchise Turnkey Deliverables Ribbon (Mobile 2x2 Clean Grid) -->
        <div class="mt-10 sm:mt-12 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 text-center">
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.05] border border-white/10 flex flex-col items-center justify-center">
                <i data-lucide="layout-dashboard" class="w-5 h-5 text-amber-400 mb-1.5"></i>
                <span class="text-xs font-bold text-white block">Turnkey 3D Blueprint</span>
                <span class="text-[10px] text-white/50 block">Standardized Architecture</span>
            </div>
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.05] border border-white/10 flex flex-col items-center justify-center">
                <i data-lucide="database" class="w-5 h-5 text-emerald-400 mb-1.5"></i>
                <span class="text-xs font-bold text-white block">Cloud ERP System</span>
                <span class="text-[10px] text-white/50 block">Auto Invoicing & Inventory</span>
            </div>
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.05] border border-white/10 flex flex-col items-center justify-center">
                <i data-lucide="graduation-cap" class="w-5 h-5 text-sky-400 mb-1.5"></i>
                <span class="text-xs font-bold text-white block">EV Academy Training</span>
                <span class="text-[10px] text-white/50 block">Certified Service Staff</span>
            </div>
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white/[0.05] border border-white/10 flex flex-col items-center justify-center">
                <i data-lucide="headset" class="w-5 h-5 text-purple-400 mb-1.5"></i>
                <span class="text-xs font-bold text-white block">Dedicated Dealer Desk</span>
                <span class="text-[10px] text-white/50 block">Direct Priority Support</span>
            </div>
        </div>

        <!-- Central Action CTA (Responsive full-width on mobile) -->
        <div class="mt-8 sm:mt-10 flex flex-col sm:flex-row justify-center items-center gap-3 sm:gap-4">
            <a href="franchise/index.php" class="w-full sm:w-auto btn-brand-primary px-8 sm:px-9 py-3.5 sm:py-4 rounded-full text-xs sm:text-sm font-extrabold inline-flex items-center justify-center gap-2.5 group shadow-2xl">
                <span>Franchise Partner Portal Login</span>
                <i data-lucide="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1"></i>
            </a>
            <a href="https://wa.me/910000000000?text=Hello%20ELECTRINC,%20I%20am%20interested%20in%20setting%20up%20a%20new%20EV%20Dealership%20Showroom." target="_blank" class="w-full sm:w-auto btn-brand-ghost px-7 sm:px-8 py-3.5 sm:py-4 rounded-full text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-2 hover:bg-white/20">
                <i data-lucide="message-circle" class="w-4 h-4 text-emerald-400"></i>
                <span>Direct Dealership WhatsApp</span>
            </a>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer id="contact" class="pt-16 pb-10 bg-[#040E09] text-white/70 border-t border-white/10 relative overflow-hidden">
    <!-- Top Direct Contact Cards -->
    <div class="max-w-7xl mx-auto px-5 sm:px-6 mb-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4 hover:border-emerald-500/40 transition">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                    <i data-lucide="phone-call" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-white/50 tracking-wider block">Customer & Dealer Hotline</span>
                    <a href="tel:+910000000000" class="text-sm font-black text-white hover:text-emerald-400 transition font-mono">+91 00000 00000</a>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4 hover:border-amber-500/40 transition">
                <div class="w-11 h-11 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center shrink-0">
                    <i data-lucide="mail" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-white/50 tracking-wider block">Official Dealership Desk</span>
                    <a href="mailto:dealers@electrinc.in" class="text-sm font-black text-white hover:text-amber-400 transition font-mono">dealers@electrinc.in</a>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white/[0.04] border border-white/10 flex items-center gap-4 hover:border-sky-500/40 transition">
                <div class="w-11 h-11 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
                    <i data-lucide="map-pin" class="w-5 h-5"></i>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-white/50 tracking-wider block">National Presence</span>
                    <span class="text-sm font-black text-white font-mono">150+ Hubs Across 22 States</span>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-5 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 pb-12 border-b border-white/10">
            <!-- Brand Column -->
            <div class="md:col-span-2">
                <img src="assets/electrinc-logo.png" alt="ELECTRINC" class="h-9 w-auto object-contain">
                <p class="mt-4 text-xs sm:text-sm text-white/70 max-w-sm leading-relaxed">
                    A venture of Incredible Group of Companies. India's certified lithium-ion electric rickshaws, scooters and sports bikes — sold and serviced through our pan-India franchise network.
                </p>
                <div class="mt-5 inline-flex items-center gap-2.5 px-3 py-1.5 rounded-full bg-white/[0.06] border border-white/15 text-xs font-bold text-white">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span>Clean Energy. Brighter Business.</span>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-white">Quick Navigation</p>
                <div class="mt-4 flex flex-col gap-2.5 text-xs">
                    <a href="#top" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Home</a>
                    <a href="#products" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Showroom Fleet</a>
                    <a href="#why-us" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Technology & BMS</a>
                    <a href="#franchise" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Dealership Tiers</a>
                    <a href="member/login.php" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Member Login</a>
                    <a href="franchise/index.php" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Franchise Partner Login</a>
                    <a href="admin/login.php" class="hover:text-emerald-400 transition flex items-center gap-1.5"><i data-lucide="chevron-right" class="w-3 h-3 text-emerald-500"></i> Admin Portal</a>
                </div>
            </div>

            <!-- Certified Standards & Compliance -->
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-white">Engineering Standards</p>
                <div class="mt-4 space-y-3 text-xs text-white/70">
                    <div class="p-3 rounded-xl bg-white/[0.04] border border-white/10">
                        <span class="text-amber-400 font-bold block">100% Lithium Architecture</span>
                        <span class="text-[11px] text-white/50 mt-0.5 block">High energy density chemistry with smart micro-BMS thermal protection.</span>
                    </div>
                    <div class="p-3 rounded-xl bg-white/[0.04] border border-white/10">
                        <span class="text-emerald-400 font-bold block">Monocrystalline Solar</span>
                        <span class="text-[11px] text-white/50 mt-0.5 block">Integrated rooftop solar cells for continuous zero-cost commuter range.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-white/50">
            <p>&copy; <?php echo date('Y'); ?> ELECTRINC — a venture of Incredible Group of Companies. All rights reserved.</p>
            <p class="flex items-center gap-2">
                <span>Designed for Zero-Emission Mobility</span>
                <span>·</span>
                <span class="text-emerald-400">Made for India</span>
            </p>
        </div>
    </div>
</footer>

<!-- ============ VEHICLE SPEC INSPECTOR POPUP MODAL ============ -->
<div id="specModal" class="fixed inset-0 z-50 bg-[#0A1F16]/80 backdrop-blur-md flex items-center justify-center p-3 sm:p-6 opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white rounded-3xl max-w-xl w-full overflow-hidden shadow-2xl border border-gray-100 transform scale-95 transition-transform duration-300 max-h-[92vh] flex flex-col" id="specModalBox">
        <div class="relative bg-gradient-to-b from-[#F6F3EC] to-white p-4 sm:p-6 border-b border-[#EBE5D8] flex items-center justify-center max-h-[220px] sm:max-h-[270px] shrink-0">
            <button type="button" id="specModalClose" class="absolute top-3.5 right-3.5 sm:top-4 sm:right-4 w-9 h-9 rounded-full bg-black/10 hover:bg-black/20 flex items-center justify-center transition text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <span id="modalCatBadge" class="absolute top-3.5 left-3.5 sm:top-4 sm:left-4 text-[10px] font-bold uppercase tracking-wide px-3 py-1 rounded-full text-white bg-[#0A1F16]">
                E-Scooter
            </span>
            <img id="modalImg" src="" alt="" class="max-h-[170px] sm:max-h-[200px] w-auto object-contain drop-shadow-lg">
        </div>
        <div class="p-4 sm:p-6 overflow-y-auto no-scrollbar">
            <h3 id="modalName" class="text-xl sm:text-2xl font-black text-[#0A1F16]">Vehicle Name</h3>
            <p id="modalTag" class="text-xs font-bold text-[#E0700C] mt-0.5">Vehicle Tagline</p>
            
            <!-- 2x2 Clean Mobile Spec Grid, 4-Col Desktop Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-2.5 mt-4 sm:mt-5 p-3 sm:p-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-center">
                <div class="p-2 rounded-xl bg-white border border-gray-100">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Tested Range</span>
                    <span id="modalRange" class="text-xs sm:text-sm font-black text-emerald-700 font-mono-num mt-0.5 block">100 KM+</span>
                </div>
                <div class="p-2 rounded-xl bg-white border border-gray-100">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Battery Spec</span>
                    <span id="modalBattery" class="text-[11px] sm:text-xs font-extrabold text-gray-800 leading-tight block mt-0.5">Lithium</span>
                </div>
                <div class="p-2 rounded-xl bg-white border border-gray-100">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Top Speed</span>
                    <span id="modalSpeed" class="text-xs sm:text-sm font-black text-amber-700 font-mono-num mt-0.5 block">50 KM/H</span>
                </div>
                <div class="p-2 rounded-xl bg-white border border-gray-100">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Warranty</span>
                    <span id="modalWarranty" class="text-xs sm:text-sm font-black text-blue-700 mt-0.5 block">36 Months</span>
                </div>
            </div>

            <div class="mt-5 sm:mt-6 flex flex-col sm:flex-row items-center gap-2.5 sm:gap-3">
                <a id="modalWhatsappBtn" href="https://wa.me/910000000000" target="_blank" class="w-full sm:flex-1 py-3 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Inquire on WhatsApp
                </a>
                <a href="#contact" onclick="closeSpecModal();" class="w-full sm:flex-1 py-3 rounded-full border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-xs flex items-center justify-center gap-2 transition">
                    <i data-lucide="phone" class="w-4 h-4"></i> Call Dealership
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============ SIDE-BY-SIDE EV COMPARATOR MODAL ============ -->
<div id="compareModal" class="fixed inset-0 z-50 bg-[#0A1F16]/85 backdrop-blur-md flex items-center justify-center p-2 sm:p-6 opacity-0 pointer-events-none transition-opacity duration-300">
    <div class="bg-white rounded-3xl max-w-4xl w-full overflow-hidden shadow-2xl border border-gray-100 max-h-[92vh] flex flex-col transform scale-95 transition-transform duration-300" id="compareModalBox">
        <div class="px-4 sm:px-6 py-3.5 sm:py-4 bg-gradient-to-r from-[#0A1F16] to-[#123B27] text-white flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <i data-lucide="arrow-left-right" class="w-4 h-4 text-emerald-400"></i>
                <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider">Side-by-Side EV Comparison</h3>
            </div>
            <button type="button" onclick="closeCompareModal();" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="overflow-y-auto p-4 sm:p-6 no-scrollbar" id="compareModalContent"></div>
    </div>
</div>

<!-- ============ FLOATING COMPARE DOCK TRAY ============ -->
<div id="compareTray" class="fixed bottom-20 sm:bottom-6 left-1/2 -translate-x-1/2 z-40 bg-[#0A1F16]/95 backdrop-blur-md border border-white/20 rounded-full px-3.5 sm:px-5 py-2 sm:py-2.5 shadow-2xl text-white flex items-center gap-2.5 sm:gap-4 transition-all duration-300 transform translate-y-36 opacity-0 pointer-events-none max-w-[calc(100vw-1.5rem)]">
    <div class="flex items-center gap-2 shrink-0">
        <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
        <span class="text-xs font-bold font-mono-num" id="compareTrayCount">Comparing (0/2)</span>
    </div>
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar max-w-[140px] sm:max-w-none" id="compareTrayThumbs"></div>
    <div class="flex items-center gap-1.5 sm:gap-2 pl-2 border-l border-white/20 shrink-0">
        <button type="button" id="btnLaunchCompare" onclick="openCompareModal();" class="px-3 sm:px-4 py-1.5 rounded-full bg-gradient-to-r from-amber-500 to-amber-600 text-white font-black text-xs shadow disabled:opacity-40 disabled:cursor-not-allowed transition hover:scale-105" disabled>
            Compare
        </button>
        <button type="button" onclick="clearCompare();" class="text-white/60 hover:text-white text-xs px-1.5" title="Clear All">✕</button>
    </div>
</div>

<!-- ============ FLOATING SPEED-DIAL / BACK TO TOP ============ -->
<div class="fixed bottom-20 sm:bottom-6 right-3 sm:right-6 z-30 pointer-events-auto">
    <!-- Back To Top Button -->
    <button type="button" id="backToTopBtn" class="w-10 h-10 rounded-full bg-white border border-gray-200 text-gray-700 shadow-xl flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 hover:scale-110 hover:bg-emerald-50 hover:text-emerald-700 active:scale-95" title="Back to Top">
        <i data-lucide="chevron-up" class="w-5 h-5"></i>
    </button>
</div>

<!-- ============ PRO MOBILE APP BOTTOM DOCK (Visible on Mobile/Tablet only) ============ -->
<div class="fixed bottom-3 inset-x-3 z-30 lg:hidden pointer-events-auto">
    <div class="bg-[#0A1F16]/95 backdrop-blur-2xl border border-white/20 rounded-full px-4 py-2 shadow-[0_16px_40px_rgba(0,0,0,0.6)] flex items-center justify-between text-white max-w-md mx-auto">
        <a href="#top" class="flex flex-col items-center gap-0.5 text-white/70 hover:text-white transition px-2 py-1">
            <i data-lucide="home" class="w-4 h-4 text-emerald-400"></i>
            <span class="text-[9px] font-bold">Home</span>
        </a>
        <a href="#products" class="flex flex-col items-center gap-0.5 text-white/70 hover:text-white transition px-2 py-1">
            <i data-lucide="layers" class="w-4 h-4 text-amber-400"></i>
            <span class="text-[9px] font-bold">Fleet</span>
        </a>
        <a href="#why-us" class="flex flex-col items-center gap-0.5 text-white/70 hover:text-white transition px-2 py-1">
            <i data-lucide="cpu" class="w-4 h-4 text-sky-400"></i>
            <span class="text-[9px] font-bold">Tech</span>
        </a>
        <a href="#franchise" class="flex flex-col items-center gap-0.5 text-white/70 hover:text-white transition px-2 py-1">
            <i data-lucide="handshake" class="w-4 h-4 text-emerald-400"></i>
            <span class="text-[9px] font-bold">Franchise</span>
        </a>
        <a href="https://wa.me/910000000000?text=Hello%20ELECTRINC,%20I%20am%20inquiring%20from%20mobile%20website" target="_blank" class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-gradient-to-r from-emerald-500 to-green-600 text-white font-extrabold text-[11px] shadow-md hover:scale-105 active:scale-95 transition">
            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
            <span>Chat</span>
        </a>
    </div>
</div>

<!-- ============ JAVASCRIPT ENGINE ============ -->
<script>
    if (typeof lucide !== 'undefined') lucide.createIcons();

    /* 1. SCROLL REVEAL OBSERVER */
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-revealed');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

    /* 2. HERO MOUSE SPOTLIGHT TRACER & 3D TILT */
    const heroSection = document.getElementById('top');
    const heroSpotlight = document.getElementById('heroSpotlight');
    if (heroSection && heroSpotlight) {
        heroSection.addEventListener('mousemove', (e) => {
            const rect = heroSection.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            heroSpotlight.style.transform = `translate3d(${x - 260}px, ${y - 260}px, 0)`;
            heroSpotlight.style.opacity = '1';
        });
        heroSection.addEventListener('mouseleave', () => {
            heroSpotlight.style.opacity = '0';
        });
    }

    const stageWrap = document.querySelector('.stage-3d-wrap');
    if (stageWrap) {
        stageWrap.addEventListener('mousemove', (e) => {
            const rect = stageWrap.getBoundingClientRect();
            const x = (e.clientX - rect.left) / rect.width - 0.5;
            const y = (e.clientY - rect.top) / rect.height - 0.5;
            stageWrap.style.transform = `perspective(1000px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg) scale3d(1.02, 1.02, 1.02)`;
        });
        stageWrap.addEventListener('mouseleave', () => {
            stageWrap.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) scale3d(1, 1, 1)';
        });
    }

    /* 3. HERO VEHICLE LASER SCAN-WIPE TRANSITION */
    (function () {
        const slides = document.querySelectorAll('.hero-slide');
        const thumbBtns = document.querySelectorAll('.hero-thumb-btn');
        const nameEl = document.getElementById('heroSlideName');
        const rangeEl = document.getElementById('heroSlideRange');
        const laserBeam = document.getElementById('laserScanBeam');

        const cockpitRange = document.getElementById('cockpitRange');
        const cockpitBattery = document.getElementById('cockpitBattery');
        const cockpitWarranty = document.getElementById('cockpitWarranty');
        const cockpitSpeed = document.getElementById('cockpitSpeed');
        const quickWa = document.getElementById('heroQuickWhatsapp');

        if (!slides.length) return;

        let curIdx = 0;
        let timer = null;
        let scanAnimId = null;

        function runScanWipe(fromSlide, toSlide, onComplete) {
            if (scanAnimId) {
                cancelAnimationFrame(scanAnimId);
                scanAnimId = null;
                fromSlide.classList.remove('active');
                fromSlide.style.clipPath = '';
                fromSlide.classList.remove('scanning-in');
            }

            if (!laserBeam) {
                fromSlide.classList.remove('active');
                toSlide.classList.add('active');
                if (onComplete) onComplete();
                return;
            }

            laserBeam.classList.remove('hidden');
            laserBeam.style.top = '0%';

            toSlide.classList.add('scanning-in');
            toSlide.style.opacity = '1';
            toSlide.style.zIndex = '10';
            toSlide.style.clipPath = 'inset(0 0 100% 0)';

            const duration = 650; // Smooth 650ms cyber laser sweep
            const startTime = performance.now();

            function frame(now) {
                const elapsed = now - startTime;
                const p = Math.min(1, elapsed / duration);
                const ease = p < 0.5 ? 2 * p * p : -1 + (4 - 2 * p) * p;

                laserBeam.style.top = (ease * 100) + '%';
                toSlide.style.clipPath = `inset(0 0 ${(1 - ease) * 100}% 0)`;

                if (p < 1) {
                    scanAnimId = requestAnimationFrame(frame);
                } else {
                    laserBeam.classList.add('hidden');
                    toSlide.style.clipPath = '';
                    toSlide.style.zIndex = '';
                    toSlide.style.opacity = '';
                    toSlide.classList.remove('scanning-in');
                    toSlide.classList.add('active');
                    fromSlide.classList.remove('active');
                    scanAnimId = null;
                    if (onComplete) onComplete();
                }
            }

            scanAnimId = requestAnimationFrame(frame);
        }

        function showSlide(newIdx) {
            if (newIdx === curIdx) return;

            const fromSlide = slides[curIdx];
            const toSlide = slides[newIdx];

            // Update switcher thumbnail pills
            if (thumbBtns[curIdx]) {
                thumbBtns[curIdx].classList.remove('bg-white/20', 'border-white/40', 'text-white', 'shadow-md');
                thumbBtns[curIdx].classList.add('bg-white/5', 'border-white/10', 'text-white/60');
                const dot = thumbBtns[curIdx].querySelector('span');
                if (dot) {
                    dot.classList.remove('bg-amber-400', 'scale-125');
                    dot.classList.add('bg-white/30');
                }
            }

            if (thumbBtns[newIdx]) {
                thumbBtns[newIdx].classList.add('bg-white/20', 'border-white/40', 'text-white', 'shadow-md');
                thumbBtns[newIdx].classList.remove('bg-white/5', 'border-white/10', 'text-white/60');
                const dot = thumbBtns[newIdx].querySelector('span');
                if (dot) {
                    dot.classList.add('bg-amber-400', 'scale-125');
                    dot.classList.remove('bg-white/30');
                }
            }

            // Update Cockpit Dials Immediately
            if (nameEl) nameEl.textContent = toSlide.dataset.name;
            if (rangeEl) rangeEl.textContent = toSlide.dataset.range + ' Range';

            if (cockpitRange && toSlide.dataset.range) cockpitRange.textContent = toSlide.dataset.range;
            if (cockpitBattery && toSlide.dataset.battery) cockpitBattery.textContent = toSlide.dataset.battery;
            if (cockpitWarranty && toSlide.dataset.warranty) cockpitWarranty.textContent = toSlide.dataset.warranty;
            if (cockpitSpeed && toSlide.dataset.speed) cockpitSpeed.textContent = toSlide.dataset.speed;

            // Update Mobile Cockpit Telemetry Strip
            const mRange = document.getElementById('mobileCockpitRange');
            const mBattery = document.getElementById('mobileCockpitBattery');
            const mWarranty = document.getElementById('mobileCockpitWarranty');
            const mSpeed = document.getElementById('mobileCockpitSpeed');
            const mWa = document.getElementById('heroQuickWhatsappMobile');

            if (mRange && toSlide.dataset.range) mRange.textContent = toSlide.dataset.range;
            if (mBattery && toSlide.dataset.battery) mBattery.textContent = toSlide.dataset.battery;
            if (mWarranty && toSlide.dataset.warranty) mWarranty.textContent = toSlide.dataset.warranty;
            if (mSpeed && toSlide.dataset.speed) mSpeed.textContent = toSlide.dataset.speed;
            if (mWa) {
                const msg = encodeURIComponent(`Hello ELECTRINC, I want to inquire about ${toSlide.dataset.name} (${toSlide.dataset.tag}).`);
                mWa.href = `https://wa.me/910000000000?text=${msg}`;
            }

            if (quickWa) {
                const msg = encodeURIComponent(`Hello ELECTRINC, I want to inquire about ${toSlide.dataset.name} (${toSlide.dataset.tag}).`);
                quickWa.href = `https://wa.me/910000000000?text=${msg}`;
            }

            // Run Laser Scan-Wipe Transition
            runScanWipe(fromSlide, toSlide, () => {
                curIdx = newIdx;
            });
        }

        function startAuto() {
            stopAuto();
            timer = setInterval(() => {
                const next = (curIdx + 1) % slides.length;
                showSlide(next);
            }, 4600);
        }

        function stopAuto() {
            if (timer) {
                clearInterval(timer);
                timer = null;
            }
        }

        thumbBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const sIdx = parseInt(btn.dataset.slide, 10);
                showSlide(sIdx);
                startAuto();
            });
        });

        if (stageWrap) {
            stageWrap.addEventListener('mouseenter', stopAuto);
            stageWrap.addEventListener('mouseleave', startAuto);
        }

        startAuto();
    })();

    /* 4. BACK TO TOP BUTTON */
    const backToTopBtn = document.getElementById('backToTopBtn');
    window.addEventListener('scroll', () => {
        const scrollTop = window.scrollY || document.documentElement.scrollTop;
        if (backToTopBtn) {
            if (scrollTop > 380) {
                backToTopBtn.classList.remove('opacity-0', 'pointer-events-none');
                backToTopBtn.classList.add('opacity-100', 'pointer-events-auto');
            } else {
                backToTopBtn.classList.add('opacity-0', 'pointer-events-none');
                backToTopBtn.classList.remove('opacity-100', 'pointer-events-auto');
            }
        }
    });

    if (backToTopBtn) {
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /* 5. PRO MOBILE DRAWER CONTROLLER */
    window.toggleMobileMenu = function (force) {
        const menu = document.getElementById('mobileMenu');
        const backdrop = document.getElementById('mobileBackdrop');
        const burgerIcon = document.getElementById('burgerIcon');
        const closeIcon = document.getElementById('closeIcon');
        if (!menu) return;

        const isCurrentlyHidden = menu.classList.contains('hidden');
        const shouldOpen = (typeof force === 'boolean') ? force : isCurrentlyHidden;

        if (shouldOpen) {
            menu.classList.remove('hidden');
            if (backdrop) backdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            if (burgerIcon) burgerIcon.classList.add('hidden');
            if (closeIcon) closeIcon.classList.remove('hidden');
        } else {
            menu.classList.add('hidden');
            if (backdrop) backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            if (burgerIcon) burgerIcon.classList.remove('hidden');
            if (closeIcon) closeIcon.classList.add('hidden');
        }
    };

    /* 6. FLEET FILTER & INSTANT LIVE SEARCH */
    function filterFleetCards() {
        const activeFilter = document.querySelector('#filterBar .filter-pill.active')?.dataset.filter || 'all';
        const query = (document.getElementById('quickSearchFleet')?.value || '').toLowerCase().trim();
        const cards = document.querySelectorAll('#productGrid > div');
        let matchedCount = 0;

        cards.forEach((card) => {
            const matchesCat = (activeFilter === 'all' || card.dataset.cat === activeFilter);
            const name = card.dataset.modelName || '';
            const matchesQuery = (!query || name.includes(query));

            if (matchesCat && matchesQuery) {
                card.style.display = '';
                card.style.animation = `cardFadeScaleIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards ${matchedCount * 30}ms`;
                matchedCount++;
            } else {
                card.style.display = 'none';
            }
        });
    }

    document.querySelectorAll('#filterBar .filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#filterBar .filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterFleetCards();
        });
    });

    const quickSearchInput = document.getElementById('quickSearchFleet');
    if (quickSearchInput) {
        quickSearchInput.addEventListener('input', () => {
            filterFleetCards();
        });
    }

    /* 7. QUICK SPEC MODAL */
    const specModal = document.getElementById('specModal');
    const specModalBox = document.getElementById('specModalBox');
    const specModalClose = document.getElementById('specModalClose');

    function openSpecModal(data) {
        document.getElementById('modalName').textContent = data.name;
        document.getElementById('modalTag').textContent = data.tag;
        document.getElementById('modalRange').textContent = data.range;
        document.getElementById('modalBattery').textContent = data.battery;
        document.getElementById('modalWarranty').textContent = data.warranty;
        document.getElementById('modalSpeed').textContent = data.speed;
        document.getElementById('modalCatBadge').textContent = data.cat;
        document.getElementById('modalImg').src = 'assets/ev-products/' + data.img;
        document.getElementById('modalImg').alt = data.name;

        const waMsg = encodeURIComponent(`Hello ELECTRINC, I want to inquire about ${data.name} (${data.tag}). Please share detailed brochure and dealership pricing.`);
        document.getElementById('modalWhatsappBtn').href = `https://wa.me/910000000000?text=${waMsg}`;

        specModal.classList.remove('opacity-0', 'pointer-events-none');
        specModal.classList.add('opacity-100', 'pointer-events-auto');
        specModalBox.classList.remove('scale-95');
        specModalBox.classList.add('scale-100');
        document.body.classList.add('overflow-hidden');
    }

    function closeSpecModal() {
        specModal.classList.add('opacity-0', 'pointer-events-none');
        specModal.classList.remove('opacity-100', 'pointer-events-auto');
        specModalBox.classList.add('scale-95');
        specModalBox.classList.remove('scale-100');
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('.spec-modal-trigger').forEach(btn => {
        btn.addEventListener('click', () => {
            openSpecModal({
                name: btn.dataset.name,
                img: btn.dataset.img,
                cat: btn.dataset.cat,
                tag: btn.dataset.tag,
                range: btn.dataset.range,
                battery: btn.dataset.battery,
                warranty: btn.dataset.warranty,
                speed: btn.dataset.speed || '50 KM/H',
                charge: btn.dataset.charge || '3–4 Hrs'
            });
        });
    });

    if (specModalClose) specModalClose.addEventListener('click', closeSpecModal);
    if (specModal) {
        specModal.addEventListener('click', (e) => {
            if (e.target === specModal) closeSpecModal();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && specModal && !specModal.classList.contains('pointer-events-none')) {
            closeSpecModal();
        }
    });

    /* 8. SMART EV MATCHER WIZARD */
    let matcherSelectedPurpose = null;
    let matcherSelectedRange = null;

    function toggleSmartMatcher() {
        const panel = document.getElementById('smartMatcherPanel');
        const chevron = document.getElementById('matcherChevron');
        if (!panel) return;
        panel.classList.toggle('hidden');
        if (chevron) chevron.classList.toggle('rotate-180');
    }

    function selectMatcherPurpose(btn, purpose) {
        matcherSelectedPurpose = purpose;
        document.querySelectorAll('#matcherPurposeGroup .matcher-btn').forEach(b => {
            b.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
            b.classList.add('border-gray-200', 'bg-white');
        });
        btn.classList.add('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
        btn.classList.remove('border-gray-200', 'bg-white');
        evaluateMatcher();
    }

    function selectMatcherRange(btn, rangeType) {
        matcherSelectedRange = rangeType;
        document.querySelectorAll('#matcherRangeGroup .matcher-range-btn').forEach(b => {
            b.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
            b.classList.add('border-gray-200', 'bg-white');
        });
        btn.classList.add('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
        btn.classList.remove('border-gray-200', 'bg-white');
        evaluateMatcher();
    }

    function evaluateMatcher() {
        if (!matcherSelectedPurpose) return;
        const cards = document.querySelectorAll('#productGrid > div');
        let matchedCount = 0;

        cards.forEach(card => {
            const cat = card.dataset.cat;
            const name = card.dataset.modelName || '';
            const rangeVal = parseInt(card.dataset.rangeVal, 10) || 50;

            let matches = false;
            if (matcherSelectedPurpose === 'commute') {
                matches = (cat === 'scooter' || cat === 'bike');
            } else if (matcherSelectedPurpose === 'passenger') {
                matches = (cat === 'rickshaw' && !name.includes('loader'));
            } else if (matcherSelectedPurpose === 'cargo') {
                matches = (name.includes('loader') || cat === 'rickshaw');
            } else if (matcherSelectedPurpose === 'sport') {
                matches = (cat === 'bike' || name.includes('mania') || name.includes('m2'));
            }

            if (matches && matcherSelectedRange) {
                if (matcherSelectedRange === 'low' && rangeVal > 70) matches = false;
                if (matcherSelectedRange === 'mid' && (rangeVal < 55 || rangeVal > 115)) matches = false;
                if (matcherSelectedRange === 'high' && rangeVal < 100) matches = false;
            }

            if (matches) {
                card.style.display = '';
                card.classList.add('ring-4', 'ring-emerald-500', 'scale-[1.02]');
                matchedCount++;
            } else {
                card.classList.remove('ring-4', 'ring-emerald-500', 'scale-[1.02]');
            }
        });

        const resultBadge = document.getElementById('matcherResultBadge');
        const resultText = document.getElementById('matcherResultText');
        if (resultBadge && resultText) {
            resultBadge.classList.remove('hidden');
            resultText.textContent = `🎯 Found ${matchedCount} recommended EV models highlighted below!`;
        }
    }

    function resetSmartMatcher() {
        matcherSelectedPurpose = null;
        matcherSelectedRange = null;
        document.querySelectorAll('#matcherPurposeGroup .matcher-btn').forEach(b => {
            b.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
            b.classList.add('border-gray-200', 'bg-white');
        });
        document.querySelectorAll('#matcherRangeGroup .matcher-range-btn').forEach(b => {
            b.classList.remove('border-emerald-600', 'bg-emerald-50', 'text-emerald-800');
            b.classList.add('border-gray-200', 'bg-white');
        });
        document.querySelectorAll('#productGrid > div').forEach(c => {
            c.style.display = '';
            c.classList.remove('ring-4', 'ring-emerald-500', 'scale-[1.02]');
        });
        const resultBadge = document.getElementById('matcherResultBadge');
        if (resultBadge) resultBadge.classList.add('hidden');
    }

    /* 9. SIDE-BY-SIDE FLEET COMPARATOR ENGINE */
    let comparedVehicles = [];

    function updateCompareTray() {
        const tray = document.getElementById('compareTray');
        const countLabel = document.getElementById('compareTrayCount');
        const thumbList = document.getElementById('compareTrayThumbs');
        const btnLaunch = document.getElementById('btnLaunchCompare');

        if (!tray || !countLabel || !thumbList) return;

        if (comparedVehicles.length > 0) {
            tray.classList.remove('translate-y-28', 'opacity-0', 'pointer-events-none');
            tray.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
        } else {
            tray.classList.add('translate-y-28', 'opacity-0', 'pointer-events-none');
            tray.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
        }

        countLabel.textContent = `Comparing (${comparedVehicles.length}/2)`;

        thumbList.innerHTML = comparedVehicles.map((v, i) => `
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/10 border border-white/20 text-xs">
                <img src="assets/ev-products/${v.img}" alt="" class="w-5 h-5 rounded-full object-cover">
                <span class="font-bold text-[11px] truncate max-w-[90px]">${v.name}</span>
                <button type="button" onclick="removeCompareVehicle(${i});" class="text-white/60 hover:text-white ml-0.5">✕</button>
            </div>
        `).join('');

        if (btnLaunch) {
            btnLaunch.disabled = (comparedVehicles.length !== 2);
        }
    }

    function toggleCompareVehicle(btn) {
        const vData = {
            name: btn.dataset.name,
            img: btn.dataset.img,
            cat: btn.dataset.cat,
            tag: btn.dataset.tag,
            range: btn.dataset.range,
            battery: btn.dataset.battery,
            warranty: btn.dataset.warranty,
            speed: btn.dataset.speed,
            charge: btn.dataset.charge
        };

        const existingIdx = comparedVehicles.findIndex(v => v.name === vData.name);
        if (existingIdx !== -1) {
            comparedVehicles.splice(existingIdx, 1);
            btn.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
            btn.classList.add('bg-white/95', 'text-gray-600');
            const label = btn.querySelector('.compare-btn-label');
            if (label) label.textContent = 'Compare';
        } else {
            if (comparedVehicles.length >= 2) {
                const old = comparedVehicles.shift();
                document.querySelectorAll('.compare-toggle-btn').forEach(b => {
                    if (b.dataset.name === old.name) {
                        b.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
                        b.classList.add('bg-white/95', 'text-gray-600');
                        const l = b.querySelector('.compare-btn-label');
                        if (l) l.textContent = 'Compare';
                    }
                });
            }
            comparedVehicles.push(vData);
            btn.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600');
            btn.classList.remove('bg-white/95', 'text-gray-600');
            const label = btn.querySelector('.compare-btn-label');
            if (label) label.textContent = 'Added ✓';
        }

        updateCompareTray();
    }

    function removeCompareVehicle(idx) {
        if (comparedVehicles[idx]) {
            const removed = comparedVehicles[idx];
            document.querySelectorAll('.compare-toggle-btn').forEach(b => {
                if (b.dataset.name === removed.name) {
                    b.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
                    b.classList.add('bg-white/95', 'text-gray-600');
                    const l = b.querySelector('.compare-btn-label');
                    if (l) l.textContent = 'Compare';
                }
            });
            comparedVehicles.splice(idx, 1);
            updateCompareTray();
        }
    }

    function clearCompare() {
        comparedVehicles = [];
        document.querySelectorAll('.compare-toggle-btn').forEach(b => {
            b.classList.remove('bg-emerald-600', 'text-white', 'border-emerald-600');
            b.classList.add('bg-white/95', 'text-gray-600');
            const l = b.querySelector('.compare-btn-label');
            if (l) l.textContent = 'Compare';
        });
        updateCompareTray();
    }

    document.querySelectorAll('.compare-toggle-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleCompareVehicle(btn);
        });
    });

    const compareModal = document.getElementById('compareModal');
    const compareModalBox = document.getElementById('compareModalBox');
    const compareModalContent = document.getElementById('compareModalContent');

    function openCompareModal() {
        if (comparedVehicles.length !== 2 || !compareModal || !compareModalContent) return;
        const [a, b] = comparedVehicles;

        const numA = parseInt(a.range, 10) || 60;
        const numB = parseInt(b.range, 10) || 60;
        const maxRange = Math.max(numA, numB, 150);
        const barAPct = Math.round((numA / maxRange) * 100);
        const barBPct = Math.round((numB / maxRange) * 100);

        const spdA = parseInt(a.speed, 10) || 45;
        const spdB = parseInt(b.speed, 10) || 45;
        const maxSpd = Math.max(spdA, spdB, 90);
        const spdAPct = Math.round((spdA / maxSpd) * 100);
        const spdBPct = Math.round((spdB / maxSpd) * 100);

        const waMsgA = encodeURIComponent(`Hello ELECTRINC, I am comparing ${a.name} vs ${b.name}. Please guide me on ${a.name}.`);
        const waMsgB = encodeURIComponent(`Hello ELECTRINC, I am comparing ${b.name} vs ${a.name}. Please guide me on ${b.name}.`);

        compareModalContent.innerHTML = `
            <div class="grid grid-cols-2 gap-4 sm:gap-8 pb-6 border-b border-gray-100 text-center">
                <div class="flex flex-col items-center">
                    <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full bg-slate-900 text-white mb-2">${a.cat}</span>
                    <img src="assets/ev-products/${a.img}" alt="${a.name}" class="h-32 sm:h-40 w-auto object-contain drop-shadow">
                    <h4 class="text-base sm:text-xl font-black text-[#0A1F16] mt-2">${a.name}</h4>
                    <p class="text-xs font-bold text-[#E0700C]">${a.tag}</p>
                </div>
                <div class="flex flex-col items-center">
                    <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full bg-slate-900 text-white mb-2">${b.cat}</span>
                    <img src="assets/ev-products/${b.img}" alt="${b.name}" class="h-32 sm:h-40 w-auto object-contain drop-shadow">
                    <h4 class="text-base sm:text-xl font-black text-[#0A1F16] mt-2">${b.name}</h4>
                    <p class="text-xs font-bold text-[#E0700C]">${b.tag}</p>
                </div>
            </div>

            <!-- Certified Range Comparison -->
            <div class="py-4 border-b border-gray-100">
                <div class="flex justify-between items-center text-xs font-black uppercase text-gray-400 mb-2">
                    <span>${a.name} (${a.range})</span>
                    <span class="text-emerald-700 flex items-center gap-1 font-bold"><i data-lucide="gauge" class="w-3.5 h-3.5"></i> Tested Range</span>
                    <span>${b.name} (${b.range})</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="h-3.5 bg-gray-100 rounded-full overflow-hidden flex flex-row-reverse">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-green-600 rounded-full transition-all duration-700" style="width: ${barAPct}%"></div>
                    </div>
                    <div class="h-3.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-emerald-500 to-green-600 rounded-full transition-all duration-700" style="width: ${barBPct}%"></div>
                    </div>
                </div>
            </div>

            <!-- Top Speed Comparison -->
            <div class="py-4 border-b border-gray-100">
                <div class="flex justify-between items-center text-xs font-black uppercase text-gray-400 mb-2">
                    <span>${a.speed}</span>
                    <span class="text-amber-700 flex items-center gap-1 font-bold"><i data-lucide="zap" class="w-3.5 h-3.5"></i> Top Speed</span>
                    <span>${b.speed}</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="h-3.5 bg-gray-100 rounded-full overflow-hidden flex flex-row-reverse">
                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-600 rounded-full transition-all duration-700" style="width: ${spdAPct}%"></div>
                    </div>
                    <div class="h-3.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-amber-400 to-amber-600 rounded-full transition-all duration-700" style="width: ${spdBPct}%"></div>
                    </div>
                </div>
            </div>

            <!-- Battery Architecture -->
            <div class="py-4 border-b border-gray-100 grid grid-cols-3 items-center text-center">
                <div class="text-xs sm:text-sm font-extrabold text-gray-800">${a.battery}</div>
                <div class="text-[10px] sm:text-xs font-bold uppercase text-gray-400">Battery Spec</div>
                <div class="text-xs sm:text-sm font-extrabold text-gray-800">${b.battery}</div>
            </div>

            <!-- Warranty Shield -->
            <div class="py-4 border-b border-gray-100 grid grid-cols-3 items-center text-center">
                <div class="text-xs sm:text-sm font-black text-sky-700">${a.warranty}</div>
                <div class="text-[10px] sm:text-xs font-bold uppercase text-gray-400">Warranty</div>
                <div class="text-xs sm:text-sm font-black text-sky-700">${b.warranty}</div>
            </div>

            <!-- Charging Time -->
            <div class="py-4 border-b border-gray-100 grid grid-cols-3 items-center text-center">
                <div class="text-xs font-bold text-gray-700">${a.charge}</div>
                <div class="text-[10px] sm:text-xs font-bold uppercase text-gray-400">Charging Time</div>
                <div class="text-xs font-bold text-gray-700">${b.charge}</div>
            </div>

            <!-- WhatsApp Actions for both -->
            <div class="pt-5 sm:pt-6 grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:gap-6">
                <a href="https://wa.me/910000000000?text=${waMsgA}" target="_blank" class="py-2.5 sm:py-3 px-4 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow">
                    <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Inquire ${a.name}
                </a>
                <a href="https://wa.me/910000000000?text=${waMsgB}" target="_blank" class="py-2.5 sm:py-3 px-4 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition shadow">
                    <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Inquire ${b.name}
                </a>
            </div>
        `;

        if (typeof lucide !== 'undefined') lucide.createIcons();

        compareModal.classList.remove('opacity-0', 'pointer-events-none');
        compareModal.classList.add('opacity-100', 'pointer-events-auto');
        compareModalBox.classList.remove('scale-95');
        compareModalBox.classList.add('scale-100');
        document.body.classList.add('overflow-hidden');
    }

    function closeCompareModal() {
        if (!compareModal || !compareModalBox) return;
        compareModal.classList.add('opacity-0', 'pointer-events-none');
        compareModal.classList.remove('opacity-100', 'pointer-events-auto');
        compareModalBox.classList.add('scale-95');
        compareModalBox.classList.remove('scale-100');
        document.body.classList.remove('overflow-hidden');
    }

    if (compareModal) {
        compareModal.addEventListener('click', (e) => {
            if (e.target === compareModal) closeCompareModal();
        });
    }
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && compareModal && !compareModal.classList.contains('pointer-events-none')) {
            closeCompareModal();
        }
    });

    /* 10. 3D SPATIAL MAGNETIC PARALLAX ENGINE (Desktop Pointer Only - Touch Safe) */
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        document.querySelectorAll('.studio-card-3d').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = ((y - centerY) / centerY) * -6.5;
                const rotateY = ((x - centerX) / centerX) * 6.5;

                card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-8px) scale3d(1.015, 1.015, 1.015)`;
                card.style.setProperty('--mouse-x', `${(x / rect.width * 100).toFixed(1)}%`);
                card.style.setProperty('--mouse-y', `${(y / rect.height * 100).toFixed(1)}%`);
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px) scale3d(1, 1, 1)';
            });
        });
    }
</script>

<!-- Floating Portrait Video Advertisement Component -->
<?php include_once __DIR__ . '/includes/floating-video-ad.php'; ?>
</body>
</html>
