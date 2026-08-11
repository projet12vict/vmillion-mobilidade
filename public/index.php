<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="pt-CV">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#003893">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="V-MILLION">
<link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
<link rel="icon" href="/assets/icons/icon-192.png">
<title>V-MILLION — Chega na hora. Vai com vaga. Vive a Morabeza.</title>
<meta name="description" content="V-MILLION liga passageiros a condutores de Hiace e táxi em Cabo Verde, com localização em tempo real, reservas com escolha de assento e botão SOS.">
<link rel="stylesheet" href="/assets/css/kg-design-system.css">
<link rel="stylesheet" href="/assets/css/kg-map-markers.css">
<style>
  .kg-hero {
    position: relative;
    min-height: 92vh;
    display: flex;
    align-items: center;
    overflow: hidden;
    background: var(--kg-gradient-header);
  }
  #kg-hero-map {
    position: absolute; inset: 0;
    filter: blur(3px) saturate(0.9);
    transform: scale(1.05);
    opacity: 0.55;
  }
  .kg-hero::after {
    content: "";
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(0,56,147,0.82) 0%, rgba(26,86,176,0.75) 100%);
  }
  .kg-hero__content { position: relative; z-index: 2; color: var(--kg-white); text-align: center; width: 100%; }
  .kg-hero__tagline { font-size: 1.15rem; font-weight: 600; color: var(--kg-cta); margin-bottom: var(--sp-3); }
  .kg-hero__title { font-size: 3rem; font-weight: 800; margin-bottom: var(--sp-4); }
  .kg-hero__sub { font-size: 1.125rem; opacity: 0.92; max-width: 560px; margin: 0 auto var(--sp-8); }
  .kg-hero__ctas { display: flex; gap: var(--sp-4); justify-content: center; flex-wrap: wrap; }
  .kg-btn--white-outline {
    background: transparent; color: var(--kg-white);
    border: 1.5px solid rgba(255,255,255,0.8);
  }
  .kg-btn--white-outline:hover { background: rgba(255,255,255,0.12); }

  .kg-how { padding: var(--sp-16) 0; background: var(--kg-white); }
  .kg-how__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--sp-6); margin-top: var(--sp-10); }
  .kg-how__step { text-align: center; }
  .kg-how__num {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--kg-primary); color: var(--kg-white);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; margin: 0 auto var(--sp-4);
  }

  .kg-benefits { padding: var(--sp-16) 0; background: var(--kg-bg); }
  .kg-benefits__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--sp-6); margin-top: var(--sp-10); }

  footer.kg-footer { background: var(--kg-primary-dark); color: rgba(255,255,255,0.85); padding: var(--sp-10) 0; text-align: center; }
  footer.kg-footer a { color: var(--kg-cta); text-decoration: none; }

  @media (max-width: 767px) {
    .kg-hero__title { font-size: 2rem; }
    .kg-how__grid, .kg-benefits__grid { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<section class="kg-hero">
  <div id="kg-hero-map" aria-hidden="true"></div>
  <div class="kg-hero__content kg-container">
    <p class="kg-hero__tagline">Mobilidade inteligente para Cabo Verde</p>
    <h1 class="kg-hero__title">Chega na hora.<br>Vai com vaga.<br>Vive a Morabeza.</h1>
    <p class="kg-hero__sub">O V-MILLION liga-te a condutores de Hiace e táxi com localização em tempo real, reserva de assento e segurança em cada viagem — a começar em Santiago.</p>
    <div class="kg-hero__ctas">
      <a href="/login.php?tipo=passageiro" class="kg-btn kg-btn--cta">Sou Passageiro</a>
      <a href="/login.php?tipo=condutor" class="kg-btn kg-btn--white-outline">Sou Condutor</a>
    </div>
  </div>
</section>

<section class="kg-how">
  <div class="kg-container">
    <h2 class="kg-h2" style="text-align:center;">Como funciona</h2>
    <div class="kg-how__grid">
      <div class="kg-how__step">
        <div class="kg-how__num">1</div>
        <h3 class="kg-h3">Escolhe o ponto e destino</h3>
        <p>Seleciona o teu ponto de partida e para onde vais — o sistema mostra os veículos disponíveis em tempo real.</p>
      </div>
      <div class="kg-how__step">
        <div class="kg-how__num">2</div>
        <h3 class="kg-h3">Reserva o teu lugar</h3>
        <p>Escolhe o veículo e o assento no mapa visual de 14 lugares. Sem filas, sem incerteza.</p>
      </div>
      <div class="kg-how__step">
        <div class="kg-how__num">3</div>
        <h3 class="kg-h3">Segue a viagem ao vivo</h3>
        <p>Acompanha o condutor no mapa, fala com ele via WhatsApp e usa o SOS sempre que precisares.</p>
      </div>
    </div>
  </div>
</section>

<section class="kg-benefits">
  <div class="kg-container">
    <h2 class="kg-h2" style="text-align:center;">Porquê o V-MILLION</h2>
    <div class="kg-benefits__grid">
      <div class="kg-card kg-card--hover">
        <h3 class="kg-h3">Gratuito para passageiros</h3>
        <p class="kg-small">O passageiro nunca paga nada ao sistema — apenas o preço justo da viagem.</p>
      </div>
      <div class="kg-card kg-card--hover">
        <h3 class="kg-h3">Localização em tempo real</h3>
        <p class="kg-small">GPS de alta precisão e atualizações em menos de 100ms.</p>
      </div>
      <div class="kg-card kg-card--hover">
        <h3 class="kg-h3">Segurança em primeiro lugar</h3>
        <p class="kg-small">Botão SOS e central de alarmes monitorizada pela administração.</p>
      </div>
    </div>
  </div>
</section>

<footer class="kg-footer">
  <div class="kg-container">
    <p>V-MILLION &copy; <?= date('Y') ?> — Piloto Ilha de Santiago, Cabo Verde.</p>
    <p><a href="/admin/login.php">Acesso administrativo</a></p>
  </div>
</footer>

<script src="/assets/js/kg-pwa.js"></script>
</body>
</html>
