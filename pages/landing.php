<?php
require_once __DIR__ . '/../includes/config.php';

sendNoStoreHeaders();
endAuthenticatedSession('Session closed after returning to the public landing page');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UIRI Inventory Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --uiri-navy: #09223f;
            --uiri-blue: #0d5fa8;
            --uiri-cyan: #35a7c8;
            --uiri-gold: #f7c744;
            --uiri-ink: #101828;
            --uiri-muted: #607086;
            --uiri-line: #d8e3ef;
            --uiri-paper: #f4f8fb;
            --uiri-white: #ffffff;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--uiri-ink);
            background: var(--uiri-white);
            letter-spacing: 0;
        }

        .site-shell {
            min-height: 100vh;
            background:
                linear-gradient(180deg, rgba(244,248,251,.9) 0%, rgba(255,255,255,1) 42%),
                var(--uiri-paper);
        }

        .public-nav {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 14px clamp(18px, 5vw, 72px);
            background: rgba(255,255,255,.92);
            border-bottom: 1px solid rgba(216,227,239,.78);
            backdrop-filter: blur(16px);
        }

        .brand-lockup {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 230px;
            color: inherit;
            text-decoration: none;
        }

        .brand-lockup img {
            width: 46px;
            height: 46px;
            object-fit: contain;
        }

        .brand-lockup strong {
            display: block;
            color: var(--uiri-navy);
            font-size: .95rem;
            line-height: 1.1;
        }

        .brand-lockup span {
            display: block;
            margin-top: 3px;
            color: var(--uiri-muted);
            font-size: .76rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 22px;
            flex: 1;
        }

        .nav-links a {
            color: #31425a;
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .public-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 18px;
            border-radius: 999px;
            border: 1px solid transparent;
            color: inherit;
            font-size: .9rem;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }

        .public-btn.primary {
            color: #07172a;
            background: var(--uiri-gold);
            box-shadow: 0 10px 24px rgba(247,199,68,.28);
        }

        .public-btn.secondary {
            color: var(--uiri-navy);
            background: #fff;
            border-color: #bdd0e3;
        }

        .hero {
            position: relative;
            min-height: calc(100vh - 74px);
            display: grid;
            align-items: end;
            padding: clamp(54px, 8vw, 94px) clamp(18px, 5vw, 72px) 28px;
            color: #fff;
            overflow: hidden;
            isolation: isolate;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: -2;
            background-image:
                linear-gradient(90deg, rgba(6,27,51,.94) 0%, rgba(6,27,51,.78) 43%, rgba(6,27,51,.28) 100%),
                linear-gradient(180deg, rgba(6,27,51,.16) 0%, rgba(6,27,51,.76) 100%),
                url("../assets/img/hero-technician.jpg");
            background-size: cover;
            background-position: center 25%;
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -6vw;
            bottom: -8vw;
            z-index: -1;
            width: 46vw;
            max-width: 620px;
            aspect-ratio: 1;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 50%;
            box-shadow: inset 0 0 0 36px rgba(53,167,200,.08);
        }

        .hero-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 430px;
            gap: clamp(32px, 6vw, 74px);
            align-items: end;
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
        }

        .hero-copy {
            max-width: 780px;
            padding-bottom: clamp(20px, 4vw, 48px);
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 22px;
            color: #fef7da;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 34px;
            height: 3px;
            background: var(--uiri-gold);
        }

        h1 {
            margin: 0;
            max-width: 820px;
            font-size: clamp(2.85rem, 7vw, 6.6rem);
            line-height: .92;
            color: #fff;
            letter-spacing: 0;
        }

        .hero-copy p {
            max-width: 660px;
            margin: 24px 0 0;
            color: rgba(255,255,255,.84);
            font-size: clamp(1.02rem, 1.7vw, 1.22rem);
            line-height: 1.72;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .hero-panel {
            background: rgba(255,255,255,.92);
            color: var(--uiri-ink);
            border: 1px solid rgba(255,255,255,.68);
            border-radius: 8px;
            box-shadow: 0 28px 80px rgba(0,0,0,.28);
            overflow: hidden;
        }

        .panel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            background: #fff;
            border-bottom: 1px solid var(--uiri-line);
        }

        .panel-top strong {
            color: var(--uiri-navy);
            font-size: .96rem;
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border-radius: 999px;
            color: #05603a;
            background: #dff7ea;
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .live-pill::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #12b76a;
        }

        .metric-stack {
            display: grid;
            gap: 1px;
            background: var(--uiri-line);
        }

        .metric-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            padding: 18px 20px;
            background: rgba(255,255,255,.96);
        }

        .metric-row span {
            display: block;
            color: var(--uiri-muted);
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .metric-row strong {
            display: block;
            margin-top: 6px;
            color: var(--uiri-navy);
            font-size: 1.35rem;
            line-height: 1.1;
        }

        .metric-bar {
            width: 90px;
            height: 8px;
            align-self: center;
            border-radius: 999px;
            background: #e8eef5;
            overflow: hidden;
        }

        .metric-bar i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--uiri-blue), var(--uiri-cyan));
        }

        .hero-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1px;
            max-width: 1240px;
            margin: 26px auto 0;
            background: rgba(255,255,255,.2);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 8px;
            overflow: hidden;
        }

        .hero-strip div {
            padding: 18px 20px;
            background: rgba(255,255,255,.1);
            backdrop-filter: blur(10px);
        }

        .hero-strip strong {
            display: block;
            color: #fff;
            font-size: 1.4rem;
        }

        .hero-strip span {
            display: block;
            margin-top: 6px;
            color: rgba(255,255,255,.75);
            font-size: .82rem;
            line-height: 1.45;
        }

        main {
            background: #fff;
        }

        .section {
            padding: clamp(58px, 7vw, 94px) clamp(18px, 5vw, 72px);
        }

        .section-inner {
            max-width: 1240px;
            margin: 0 auto;
        }

        .section-kicker {
            margin: 0 0 12px;
            color: var(--uiri-blue);
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .section-heading {
            display: grid;
            grid-template-columns: minmax(0, .9fr) minmax(280px, .6fr);
            gap: 32px;
            align-items: end;
            margin-bottom: 36px;
        }

        .section-heading h2 {
            margin: 0;
            color: var(--uiri-navy);
            font-size: clamp(2rem, 4vw, 3.6rem);
            line-height: 1;
            letter-spacing: 0;
        }

        .section-heading p {
            margin: 0;
            color: var(--uiri-muted);
            font-size: 1rem;
            line-height: 1.72;
        }

        .capability-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border: 1px solid var(--uiri-line);
            border-radius: 8px;
            overflow: hidden;
        }

        .capability {
            min-height: 260px;
            padding: 24px;
            border-right: 1px solid var(--uiri-line);
            background: #fff;
        }

        .capability:last-child { border-right: 0; }

        .capability span {
            display: inline-flex;
            width: 40px;
            height: 40px;
            align-items: center;
            justify-content: center;
            margin-bottom: 38px;
            border-radius: 50%;
            color: var(--uiri-navy);
            background: #fff4c7;
            font-weight: 900;
        }

        .capability h3 {
            margin: 0 0 12px;
            color: var(--uiri-navy);
            font-size: 1.14rem;
        }

        .capability p {
            margin: 0;
            color: var(--uiri-muted);
            font-size: .93rem;
            line-height: 1.65;
        }

        .system-section {
            background: var(--uiri-paper);
            border-top: 1px solid var(--uiri-line);
            border-bottom: 1px solid var(--uiri-line);
        }

        .system-grid {
            display: grid;
            grid-template-columns: minmax(0, .72fr) minmax(0, 1fr);
            gap: 34px;
            align-items: stretch;
        }

        .image-board {
            min-height: 620px;
            border-radius: 8px;
            background-image:
                linear-gradient(180deg, rgba(9,34,63,.05), rgba(9,34,63,.62)),
                url("../assets/img/system-architecture.jpg");
            background-size: cover;
            background-position: center 30%;
            overflow: hidden;
            display: flex;
            align-items: end;
            border: 1px solid var(--uiri-line);
        }

        .image-caption {
            width: 100%;
            padding: 28px;
            color: #fff;
            background: linear-gradient(180deg, rgba(9,34,63,0), rgba(9,34,63,.86));
        }

        .image-caption strong {
            display: block;
            font-size: 1.3rem;
            line-height: 1.2;
        }

        .image-caption span {
            display: block;
            margin-top: 10px;
            color: rgba(255,255,255,.78);
            line-height: 1.55;
        }

        .decision-board {
            display: grid;
            gap: 14px;
        }

        .decision-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 18px;
            padding: 22px;
            border: 1px solid var(--uiri-line);
            border-radius: 8px;
            background: #fff;
        }

        .decision-card .number {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #07172a;
            background: var(--uiri-gold);
            font-weight: 900;
        }

        .decision-card h3 {
            margin: 0 0 8px;
            color: var(--uiri-navy);
            font-size: 1.1rem;
        }

        .decision-card p {
            margin: 0;
            color: var(--uiri-muted);
            font-size: .94rem;
            line-height: 1.65;
        }

        .governance {
            display: grid;
            grid-template-columns: minmax(0, .82fr) minmax(300px, .5fr);
            gap: 34px;
            align-items: start;
        }

        .analytics-panel {
            border: 1px solid var(--uiri-line);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 24px 64px rgba(16,24,40,.08);
        }

        .analytics-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border-bottom: 1px solid var(--uiri-line);
        }

        .analytics-head strong {
            color: var(--uiri-navy);
        }

        .analytics-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: var(--uiri-line);
        }

        .analytics-cell {
            min-height: 172px;
            padding: 20px;
            background: #fff;
        }

        .analytics-cell h4 {
            margin: 0 0 18px;
            color: var(--uiri-navy);
            font-size: .86rem;
        }

        .bars {
            display: grid;
            gap: 11px;
        }

        .bar {
            display: grid;
            grid-template-columns: 82px 1fr 38px;
            gap: 10px;
            align-items: center;
            color: #53657c;
            font-size: .76rem;
            font-weight: 800;
        }

        .bar-track {
            height: 10px;
            border-radius: 999px;
            background: #e8eef5;
            overflow: hidden;
        }

        .bar-track i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: var(--uiri-blue);
        }

        .ring-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .ring {
            width: 116px;
            height: 116px;
            border-radius: 50%;
            background: conic-gradient(var(--uiri-blue) 0 48%, var(--uiri-gold) 48% 74%, #25b071 74% 91%, #e8eef5 91% 100%);
            display: grid;
            place-items: center;
        }

        .ring::after {
            content: "91%";
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: var(--uiri-navy);
            background: #fff;
            font-weight: 900;
        }

        .legend {
            display: grid;
            gap: 9px;
            color: #53657c;
            font-size: .78rem;
            font-weight: 800;
        }

        .legend span {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend i {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--dot);
        }

        .governance-copy {
            padding: 8px 0;
        }

        .governance-copy h2 {
            margin: 0;
            color: var(--uiri-navy);
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.02;
        }

        .governance-copy p {
            margin: 20px 0 0;
            color: var(--uiri-muted);
            line-height: 1.72;
        }

        .proof-list {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .proof-list div {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #31425a;
            font-weight: 800;
        }

        .proof-list div::before {
            content: "";
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--uiri-gold);
            box-shadow: 0 0 0 5px rgba(247,199,68,.18);
        }

        .cta-section {
            padding: 0 clamp(18px, 5vw, 72px) clamp(58px, 7vw, 94px);
        }

        .cta {
            max-width: 1240px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 28px;
            align-items: center;
            padding: clamp(28px, 5vw, 46px);
            border-radius: 8px;
            color: #fff;
            background:
                linear-gradient(90deg, rgba(9,34,63,1), rgba(13,95,168,.94)),
                var(--uiri-navy);
        }

        .cta h2 {
            margin: 0;
            color: #fff;
            font-size: clamp(1.7rem, 3.4vw, 3rem);
            line-height: 1;
        }

        .cta p {
            max-width: 710px;
            margin: 14px 0 0;
            color: rgba(255,255,255,.76);
            line-height: 1.68;
        }

        .footer {
            padding: 34px clamp(18px, 5vw, 72px);
            color: rgba(255,255,255,.72);
            background: #07172a;
        }

        .footer-inner {
            max-width: 1240px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 24px;
            align-items: center;
        }

        .footer strong {
            display: block;
            color: #fff;
            margin-bottom: 6px;
        }

        .footer a {
            color: #fff;
            text-decoration: none;
            font-weight: 800;
        }

        @media (max-width: 1050px) {
            .nav-links { display: none; }
            .hero-grid,
            .section-heading,
            .system-grid,
            .governance,
            .cta {
                grid-template-columns: 1fr;
            }
            .hero-panel { max-width: 540px; }
            .capability-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .capability:nth-child(2) { border-right: 0; }
            .capability:nth-child(-n+2) { border-bottom: 1px solid var(--uiri-line); }
        }

        @media (max-width: 720px) {
            .public-nav {
                padding: 12px 20px;
                /* stays flex-row, space-between */
            }
            .brand-lockup div { display: none; }
            .nav-actions { width: auto; }
            .nav-actions .public-btn {
                padding: 0;
                width: 42px;
                height: 42px;
                border-radius: 50%;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .nav-actions .public-btn .btn-text { display: none; }
            .nav-actions .public-btn .btn-icon-mobile { display: block !important; }

            .hero { min-height: auto; }
            .hero-strip,
            .capability-grid,
            .analytics-body,
            .footer-inner {
                grid-template-columns: 1fr;
            }
            .capability {
                min-height: auto;
                border-right: 0;
                border-bottom: 1px solid var(--uiri-line);
            }
            .capability:last-child { border-bottom: 0; }
            .image-board { min-height: 460px; }
            .ring-wrap {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
    <script>
        if (!Object.hasOwn) {
            Object.hasOwn = function(obj, prop) {
                return Object.prototype.hasOwnProperty.call(obj, prop);
            };
        }
    </script>
</head>
<body>
    <div class="site-shell">
        <nav class="public-nav" aria-label="Public navigation">
            <a class="brand-lockup" href="../pages/landing.php">
                <img src="../assets/img/uiri-logo.webp" alt="UIRI logo">
                <div>
                    <strong>Uganda Industrial Research Institute</strong>
                    <span>Inventory Intelligence System</span>
                </div>
            </a>
            <div class="nav-links">
                <a href="#mandate">Mandate</a>
                <a href="#system">System</a>
                <a href="#governance">Governance</a>
                <a href="#access">Access</a>
            </div>
            <div class="nav-actions">
                <a class="public-btn primary" href="../login.php">
                    <span class="btn-text">Sign In</span>
                    <svg class="btn-icon-mobile" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none; width: 18px; height: 18px;">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
                    </svg>
                </a>
            </div>
        </nav>

        <header class="hero">
            <div class="hero-grid">
                <div class="hero-copy">
                    <div class="eyebrow">Industrial research, assets and accountability</div>
                    <h1>Inventory governance for Uganda's industrial innovation engine.</h1>
                    <p>
                        A secure DBMS for UIRI campuses, laboratories, workshops and stores. Built to connect procurement, stock movement, maintenance, suppliers, audit trails and management reporting into one reliable operational record.
                    </p>
                    <div class="hero-actions">
                        <a class="public-btn primary" href="../login.php">Enter Secure Portal</a>
                        <a class="public-btn secondary" href="#system">Explore System Value</a>
                    </div>
                </div>

                <aside class="hero-panel" aria-label="Inventory intelligence preview">
                    <div class="panel-top">
                        <strong>Enterprise Control Plane</strong>
                        <span class="live-pill">Operational</span>
                    </div>
                    <div class="metric-stack">
                        <div class="metric-row">
                            <div><span>Asset traceability</span><strong>Campus to section</strong></div>
                            <div class="metric-bar"><i style="width: 92%"></i></div>
                        </div>
                        <div class="metric-row">
                            <div><span>Decision coverage</span><strong>Stock, value, risk</strong></div>
                            <div class="metric-bar"><i style="width: 86%"></i></div>
                        </div>
                        <div class="metric-row">
                            <div><span>Governance layer</span><strong>Role and audit aware</strong></div>
                            <div class="metric-bar"><i style="width: 95%"></i></div>
                        </div>
                        <div class="metric-row">
                            <div><span>Reporting depth</span><strong>Filtered printouts</strong></div>
                            <div class="metric-bar"><i style="width: 89%"></i></div>
                        </div>
                    </div>
                </aside>
            </div>

            <div class="hero-strip">
                <div><strong>01</strong><span>Procurement-to-stock visibility across suppliers, campuses and item categories.</span></div>
                <div><strong>02</strong><span>Management dashboards shaped for stock risk, value exposure and action queues.</span></div>
                <div><strong>03</strong><span>Inventory records tied to users, roles, timestamps and approval workflows.</span></div>
                <div><strong>04</strong><span>Reports designed for departments, sections, purchase dates and audit evidence.</span></div>
            </div>
        </header>
    </div>

    <main>
        <section class="section" id="mandate">
            <div class="section-inner">
                <div class="section-heading">
                    <div>
                        <p class="section-kicker">UIRI public mandate</p>
                        <h2>Supporting the assets behind industrial development.</h2>
                    </div>
                    <p>
                        UIRI's public work spans value addition, product development, business incubation, technology transfer, analytical laboratory services and industrial skills development. This system turns the physical resources behind that mandate into governed, searchable and reportable institutional data.
                    </p>
                </div>

                <div class="capability-grid">
                    <article class="capability">
                        <span>VA</span>
                        <h3>Value addition</h3>
                        <p>Track equipment and consumables used to improve raw materials, prototypes and production processes.</p>
                    </article>
                    <article class="capability">
                        <span>PD</span>
                        <h3>Product development</h3>
                        <p>Connect stock availability, supplier history and item condition to practical research and pilot production.</p>
                    </article>
                    <article class="capability">
                        <span>AL</span>
                        <h3>Analytical labs</h3>
                        <p>Give laboratory teams visibility into controlled assets, supplies, calibration exposure and maintenance status.</p>
                    </article>
                    <article class="capability">
                        <span>MS</span>
                        <h3>MMISDC Namanve</h3>
                        <p>Support manufacturing and industrial skills facilities with traceable machinery, tools and stock movement.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section system-section" id="system">
            <div class="section-inner system-grid">
                <div class="image-board">
                    <div class="image-caption">
                        <strong>Designed for institutional operations, not just item listing.</strong>
                        <span>Inventory data becomes useful when managers can see condition, status, value, movement, supplier exposure and accountability in the same system.</span>
                    </div>
                </div>

                <div>
                    <p class="section-kicker">DBMS capability</p>
                    <div class="decision-board">
                        <article class="decision-card">
                            <span class="number">1</span>
                            <div>
                                <h3>Asset lifecycle intelligence</h3>
                                <p>Capture category, status, condition, purchase date, value, supplier, campus, department, section and stock movement history.</p>
                            </div>
                        </article>
                        <article class="decision-card">
                            <span class="number">2</span>
                            <div>
                                <h3>Decision-first dashboards</h3>
                                <p>Surface inventory health, low-stock exposure, pending requests, campus comparisons and operational status in visual form.</p>
                            </div>
                        </article>
                        <article class="decision-card">
                            <span class="number">3</span>
                            <div>
                                <h3>Robust reporting filters</h3>
                                <p>Print reports by purchase year, date range, item model, specifications, supplier, status, value, campus and user activity.</p>
                            </div>
                        </article>
                        <article class="decision-card">
                            <span class="number">4</span>
                            <div>
                                <h3>Accountable workflows</h3>
                                <p>Role-based access, request approvals, stock transactions, maintenance events and audit logs keep sensitive operations traceable.</p>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section" id="governance">
            <div class="section-inner governance">
                <div class="analytics-panel">
                    <div class="analytics-head">
                        <strong>Management reporting model</strong>
                        <span class="live-pill">Insight-ready</span>
                    </div>
                    <div class="analytics-body">
                        <div class="analytics-cell">
                            <h4>Inventory value by function</h4>
                            <div class="bars">
                                <div class="bar"><span>ICT</span><div class="bar-track"><i style="width: 88%"></i></div><strong>38%</strong></div>
                                <div class="bar"><span>Labs</span><div class="bar-track"><i style="width: 74%; background:#35a7c8"></i></div><strong>29%</strong></div>
                                <div class="bar"><span>Machinery</span><div class="bar-track"><i style="width: 62%; background:#25b071"></i></div><strong>22%</strong></div>
                                <div class="bar"><span>Office</span><div class="bar-track"><i style="width: 36%; background:#f7c744"></i></div><strong>11%</strong></div>
                            </div>
                        </div>
                        <div class="analytics-cell">
                            <h4>Operational readiness</h4>
                            <div class="ring-wrap">
                                <div class="ring" aria-label="91 percent ready"></div>
                                <div class="legend">
                                    <span><i style="--dot:#0d5fa8"></i>Available</span>
                                    <span><i style="--dot:#f7c744"></i>Maintenance</span>
                                    <span><i style="--dot:#25b071"></i>Working</span>
                                    <span><i style="--dot:#d8e3ef"></i>Watchlist</span>
                                </div>
                            </div>
                        </div>
                        <div class="analytics-cell">
                            <h4>Report evidence</h4>
                            <div class="bars">
                                <div class="bar"><span>Filters</span><div class="bar-track"><i style="width: 94%; background:#25b071"></i></div><strong>Deep</strong></div>
                                <div class="bar"><span>Printout</span><div class="bar-track"><i style="width: 86%"></i></div><strong>Audited</strong></div>
                                <div class="bar"><span>Users</span><div class="bar-track"><i style="width: 78%; background:#35a7c8"></i></div><strong>Roles</strong></div>
                            </div>
                        </div>
                        <div class="analytics-cell">
                            <h4>Stock action priority</h4>
                            <div class="bars">
                                <div class="bar"><span>Low</span><div class="bar-track"><i style="width: 69%; background:#f04438"></i></div><strong>High</strong></div>
                                <div class="bar"><span>Due</span><div class="bar-track"><i style="width: 54%; background:#f7c744"></i></div><strong>Med</strong></div>
                                <div class="bar"><span>Ready</span><div class="bar-track"><i style="width: 81%; background:#25b071"></i></div><strong>Good</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="governance-copy">
                    <p class="section-kicker">Governance promise</p>
                    <h2>Built for decisions that can be defended.</h2>
                    <p>
                        A public landing page should inspire confidence; the secure application should preserve it. The IMS positions UIRI's inventory records as management evidence: current, filterable, role-aware and ready for audit.
                    </p>
                    <div class="proof-list">
                        <div>Role-based access for administrators, store managers and staff</div>
                        <div>Report printouts tied to user identity, role and timestamp</div>
                        <div>Stock movement history across receipt, issue, adjustment and transfer</div>
                        <div>Decision visuals for status, condition, risk and asset value</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta-section" id="access">
            <div class="cta">
                <div>
                    <h2>Secure access for authorised UIRI teams.</h2>
                    <p>The landing page introduces the institution and the system value. Operational records remain protected inside the authenticated portal.</p>
                </div>
                <a class="public-btn primary" href="../login.php">Proceed to Sign In</a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <div>
                <strong>Uganda Industrial Research Institute Inventory Intelligence System</strong>
                <span>Supporting accountable industrial research assets across UIRI operations.</span>
            </div>
            <a href="https://uiri.go.ug/">Visit uiri.go.ug</a>
        </div>
    </footer>
    <script>
        (function () {
            function closePublicSession() {
                var logoutUrl = '../includes/logout.php?redirect=none';
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(logoutUrl);
                    return;
                }
                fetch(logoutUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    keepalive: true
                }).catch(function () {});
            }

            window.addEventListener('pageshow', closePublicSession);
        })();
    </script>
</body>
</html>
