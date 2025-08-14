<?php
/**
 * Plugin Name: Bloco Leia Mais (Shortcode)
 * Description: Shortcode [bloco_leia_mais] com título, texto expandível e botão que herda a cor da taxonomia (term meta "cor").
 * Version: 1.0.0
 * Author: Mateus Fernandes dos Santos
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: bloco-leia-mais
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Bloqueia acesso direto
}

// Carrega a fonte Inter (Google Fonts)
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'inter-font',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
        [],
        null
    );
});

/**
 * Shortcode:
 * [bloco_leia_mais tax="para"]
 * - tax: slug da TAXONOMIA (padrão: "para")
 */
if ( ! function_exists('bloco_leia_mais_shortcode') ) {
function bloco_leia_mais_shortcode($atts = []) {
    global $post;
    if ( ! $post ) return '';

    $atts = shortcode_atts([
        'tax' => 'para',
    ], $atts, 'bloco_leia_mais');

    $titulo   = get_post_meta($post->ID, 'titulo_texto', true);
    $conteudo = get_post_meta($post->ID, 'texto', true);

    // ---------------------------
    // COR DO BOTÃO (lendo do termo da tax informada)
    // ---------------------------
    $btn_color = '#05A3E1';

    // Termos desse post na taxonomia informada
    $terms = get_the_terms($post->ID, $atts['tax']);

    // Helper para obter a cor do termo via term meta nativo
    $obter_cor_do_termo = function($term_id) {
        $cor = get_term_meta($term_id, 'cor', true); // meta key = 'cor'
        return is_string($cor) ? trim($cor) : '';
    };

    if ($terms && !is_wp_error($terms)) {

        // Se só tem 1 termo, usa ele
        if (count($terms) === 1) {
            $termoEscolhido = reset($terms);

        } else {
            // Se tiver mais de um, prioriza 'para-sua-empresa' > 'para-voce'
            $prioridades = ['para-sua-empresa', 'para-voce'];
            $termoEscolhido = null;

            foreach ($prioridades as $slugPrioritario) {
                foreach ($terms as $t) {
                    if (!empty($t->slug) && $t->slug === $slugPrioritario) {
                        $termoEscolhido = $t;
                        break 2;
                    }
                }
            }

            // fallback: primeiro termo com meta 'cor'
            if (!$termoEscolhido) {
                foreach ($terms as $t) {
                    $c = $obter_cor_do_termo($t->term_id);
                    if ($c !== '') { $termoEscolhido = $t; break; }
                }
            }

            // fallback final
            if (!$termoEscolhido) $termoEscolhido = reset($terms);
        }

        // Aplica a cor do termo escolhido (se válida)
        if (!empty($termoEscolhido)) {
            $cor = $obter_cor_do_termo($termoEscolhido->term_id);

            if ($cor !== '') {
                // aceita hex (#fff/#ffffff) ou rgb/rgba/hsl/hsla
                $eh_hex = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $cor);
                $eh_rgb_hsl = preg_match('/^(rgb|rgba|hsl|hsla)\s*\(/i', $cor);
                if ($eh_hex || $eh_rgb_hsl) {
                    $btn_color = $cor;
                }
            }
        }
    }

    // IDs únicos por instância
    $uid   = 'lm-' . $post->ID . '-' . wp_generate_password(4, false, false);
    $idTxt = $uid . '-texto';
    $idBtn = $uid . '-btn';

    ob_start(); ?>
    <div id="<?php echo esc_attr($uid); ?>" class="leia-mais-container" style="font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;">
      <?php if (!empty($titulo)): ?>
        <h2 class="leia-mais-titulo" style="font-weight:600; line-height:1.3em; margin:0 0 .5em; text-align:center;"></h2>
      <?php endif; ?>

      <div id="<?php echo esc_attr($idTxt); ?>" class="leia-mais-texto" style="position:relative; font-size:1em; line-height:1.5em; overflow:hidden; transition:max-height .35s ease;">
        <?php echo wpautop(wp_kses_post($conteudo)); ?>
      </div>

      <div style="text-align:center; margin-top:.75em;">
        <a id="<?php echo esc_attr($idBtn); ?>" class="leia-mais-link" href="javascript:void(0);" style="display:inline-block; color:#fff; background-color:<?php echo esc_attr($btn_color); ?>; font-size:1rem; padding:.6em 1em; border-radius:.5em; text-decoration:none;">
          <?php echo esc_html__('Continuar lendo ...', 'bloco-leia-mais'); ?>
        </a>
      </div>
    </div>

    <style>
      #<?php echo $uid; ?>.leia-mais-container{
        background:#fff;
        border-radius:18px;
        padding:1.25rem 1.25rem .75rem;
      }
      #<?php echo $uid; ?> .leia-mais-titulo { font-size: 3.5em; text-align:center; }
      @media (max-width: 1023.98px) { #<?php echo $uid; ?> .leia-mais-titulo { font-size: 2em; text-align:center; } }

      #<?php echo $uid; ?> .leia-mais-texto{
        --fade-height: 7em;
        -webkit-mask-image: linear-gradient(to bottom,
          rgba(0,0,0,1) calc(100% - var(--fade-height)),
          rgba(0,0,0,0) 100%);
        mask-image: linear-gradient(to bottom,
          rgba(0,0,0,1) calc(100% - var(--fade-height)),
          rgba(0,0,0,0) 100%);
      }
      #<?php echo $uid; ?> .leia-mais-texto.expandido{
        -webkit-mask-image: none;
        mask-image: none;
        overflow: visible;
      }
      @media (prefers-reduced-motion: reduce) { #<?php echo $uid; ?> .leia-mais-texto { transition: none; } }
    </style>

    <script>
      (function(){
        const titulo = <?php echo wp_json_encode($titulo); ?>;
        const tituloEl = document.querySelector('#<?php echo $uid; ?> .leia-mais-titulo');
        if (tituloEl && titulo) tituloEl.textContent = titulo;

        const btn   = document.getElementById('<?php echo $idBtn; ?>');
        const texto = document.getElementById('<?php echo $idTxt; ?>');

        let aberto = false;

        function aplicarFechado() {
          const alturaTotal   = texto.scrollHeight;
          const alturaFechada = Math.round(alturaTotal * 0.4);

          if (alturaFechada >= alturaTotal - 2) {
            texto.style.maxHeight = alturaTotal + 'px';
            texto.classList.add('expandido');
            btn.style.display = 'none';
            return;
          }

          texto.style.maxHeight = alturaFechada + 'px';
          texto.classList.remove('expandido');
          btn.innerText = 'Continuar lendo ...';
          btn.style.display = 'inline-block';
        }

        function aplicarAberto() {
          texto.style.maxHeight = texto.scrollHeight + 'px';
          texto.classList.add('expandido');
          btn.innerText = 'Mostrar menos';
        }

        aplicarFechado();

        btn.addEventListener('click', function(){
          aberto = !aberto;
          if (aberto) aplicarAberto(); else aplicarFechado();
        });

        window.addEventListener('resize', function(){
          if (!aberto) aplicarFechado();
        });
      })();
    </script>
    <?php
    return ob_get_clean();
}
}
add_shortcode('bloco_leia_mais', 'bloco_leia_mais_shortcode');

// Fim do arquivo. (Não usar tag de fechamento PHP)
