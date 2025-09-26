<?php
/**
 * MU helpers para Tutor LMS — funciones acotadas por ruta/acción
 * - Traducción del email de verificación
 * - Banner de registro OK (solo /student-registration/)
 * - Mensaje genérico + recuadro naranja en "olvidé contraseña" (solo /retrieve-password/ o tutor_retrieve_password)
 * - Errores de login genéricos
 * - Contraseña fuerte (reset + registro)
 */

/* -----------------------------
   0) Utilidades de detección
------------------------------*/
function cl_uri_has($frag){ return isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], $frag) !== false; }
function cl_is_tutor_action($regex){ return isset($_REQUEST['tutor_action']) && preg_match($regex, $_REQUEST['tutor_action']); }

/* -------------------------------------------------------
   1) Traducción al vuelo del email de verificación (Tutor)
--------------------------------------------------------*/
add_filter('wp_mail', function($args){
    $subject = $args['subject'] ?? '';
    $message = $args['message'] ?? '';

    // Detecta el correo de verificación por asunto o por el HTML típico
    $is_verify = (stripos($subject, 'verify') !== false)
              || (strpos($message, 'tutor-email-heading') !== false && stripos($message, 'verify') !== false);

    if (!$is_verify || empty($message)) return $args;

    // Asunto
    $args['subject'] = str_ireplace('Verify your Email', 'Verifica tu correo electrónico', $subject);

    // Reemplazos en el cuerpo
    $map = [
        'Email Verification' => 'Verificación de correo',
        'Verify Email Address' => 'Verificar correo',
        'Thank you for signing up for our website! To complete your account sign-up: please click on the button below to verify your email address.' =>
            'Gracias por registrarte en <strong>Atalanta Academy</strong>. Para activar tu cuenta, haz clic en el botón de abajo para verificar tu correo electrónico.',
        'If the button is unresponsive, please follow the link below and verify your email address.' =>
            'Si el botón no funciona, copia y pega el siguiente enlace en tu navegador para verificar tu correo.',
        'This is an automatically generated email. Please do not reply to this email.' =>
            'Este es un correo automático. Por favor, no respondas a este mensaje.'
    ];
    $translated = $message;
    foreach ($map as $en => $es) { $translated = str_replace($en, $es, $translated); }

    // “Hi Nombre,” -> “Hola Nombre,”
    $translated = preg_replace('/>(\s*)Hi\s+([^,<]+),(\s*)</i', '>$1Hola $2,$3<', $translated);

    // Fuerza HTML
    $headers = $args['headers'] ?? [];
    if (is_string($headers)) { $headers = [$headers]; }
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $args['message'] = $translated;
    $args['headers'] = $headers;
    return $args;
}, 50);

/* -------------------------------------------
   2) Banner de registro completado (FRONT)
   SOLO: /student-registration/  (ajusta si tu URL es otra)
--------------------------------------------*/
add_action('wp_footer', function () {
    if (!cl_uri_has('/student-registration/')) return; // <-- ajusta si tu slug es distinto

    ?>
    <script>
    (function(){
      function mark(){ try{ sessionStorage.setItem('cl_reg_submit', String(Date.now())); }catch(e){} }

      // Marca al hacer submit del formulario de Tutor
      document.addEventListener('submit', function(e){
        var f = e.target;
        if (!(f instanceof HTMLFormElement)) return;
        if (f.id === 'tutor-registration-form' ||
            f.querySelector('input[name="tutor_action"][value="tutor_register_student"]') ||
            f.querySelector('button[name="tutor_register_student_btn"]')) { mark(); }
      }, true);

      // Muestra aviso si no hay errores
      document.addEventListener('DOMContentLoaded', function(){
        var mk = sessionStorage.getItem('cl_reg_submit');
        if (!mk) return;
        sessionStorage.removeItem('cl_reg_submit');

        if (document.querySelector('.tutor-alert-danger, .tutor-error, .tutor-message-error, .error, .notice-error')) return;

        var wrap = document.getElementById('tutor-registration-wrap') || document.getElementById('tutor-registration-form')?.parentNode || document.body;
        var notice = document.createElement('div');
        notice.setAttribute('role','status');
        notice.style.background = '#e6ffed';
        notice.style.border = '1px solid #b7eb8f';
        notice.style.color = '#064420';
        notice.style.padding = '12px 16px';
        notice.style.marginBottom = '16px';
        notice.style.borderRadius = '6px';
        notice.style.fontFamily = 'system-ui,-apple-system,Segoe UI,Roboto,Arial';
        notice.innerHTML = '✅ Registro completado. Te hemos enviado un email para <strong>verificar tu correo</strong>. Revisa tu bandeja o el spam.';
        wrap.insertBefore(notice, wrap.firstChild);
        setTimeout(function(){ if (notice && notice.parentNode) notice.remove(); }, 8000);
      });
    })();
    </script>
    <?php
}, 20);

/* ------------------------------------------------------
   3) "Olvidé mi contraseña" — mensaje genérico + naranja
   SOLO: /retrieve-password/ o tutor_retrieve_password
-------------------------------------------------------*/
add_action('wp_head', function () {
    $is_lost = cl_uri_has('/retrieve-password/')
            || cl_uri_has('/lostpassword')
            || cl_is_tutor_action('/(lost|forget|retrieve)/i');

    if (!$is_lost) return;

    $GENERIC = 'Si los datos son correctos, recibirás un email con instrucciones para restablecer la contraseña.';
    ?>
    <script>document.documentElement.classList.add('js');</script>
    <style id="cl-lostpw-no-flicker">
      /* Caja naranja unificada desde el primer paint */
      .tutor-forgot-password-form .tutor-alert{background:#FFF4E5 !important;border:1px solid #F7C77E !important;color:#7A3E00 !important}
      .tutor-forgot-password-form .tutor-alert .tutor-alert-icon{color:#C05600 !important}
      /* Oculta SOLO el texto del aviso hasta que JS lo ponga genérico (evita parpadeo) */
      .js .tutor-forgot-password-form .tutor-alert .tutor-alert-text span:not(.tutor-alert-icon){visibility:hidden}
    </style>
    <script>
    (function(){
      var GENERIC = <?php echo json_encode($GENERIC); ?>;
      function apply(ctx){
        var form = ctx.querySelector('.tutor-forgot-password-form'); if(!form) return false;
        var box  = form.querySelector('.tutor-alert');               if(!box)  return false;
        var txt  = box.querySelector('.tutor-alert-text span:not(.tutor-alert-icon)'); if(!txt) return false;

        if (txt.textContent.trim() !== GENERIC) txt.textContent = GENERIC;
        txt.style.visibility = 'visible';
        box.classList.remove('tutor-success','tutor-danger','tutor-error','tutor-alert-success','tutor-alert-danger');
        return true;
      }
      if (!apply(document)) {
        var mo = new MutationObserver(function(m){ if (apply(document)) mo.disconnect(); });
        mo.observe(document.documentElement, {childList:true, subtree:true});
        setTimeout(function(){ try{ mo.disconnect(); }catch(e){} }, 8000);
      }
    })();
    </script>
    <?php
}, 20);

/* ---------------------------------------------
   4) Errores de login genéricos (no revelar causa)
----------------------------------------------*/
add_filter('authenticate', function($user, $username, $password){
    if ($user instanceof WP_Error) {
        $generic = new WP_Error();
        $generic->add('invalid_credentials', __('Credenciales no válidas. Inténtalo de nuevo.', 'atalanta'));
        return $generic;
    }
    return $user;
}, 99, 3);

add_filter('login_errors', function($msg){
    return __('Credenciales no válidas. Inténtalo de nuevo.', 'atalanta');
});

/* ---------------------------------------------
   5) Contraseña fuerte (reset password)
----------------------------------------------*/

// === Validación de contraseña fuerte y avisos en el formulario de restablecimiento ===

// Inyecta JS y CSS solo en la página /dashboard/retrieve-password/
add_action( 'wp_head', function() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // Ajusta el slug si tu URL de reset es otra
    if ( strpos( $uri, '/dashboard/retrieve-password/' ) === false ) {
        return;
    }
    ?>
    <style>
      .cl-pwweak,
      .cl-pwsuccess {
        margin: 12px 0;
        padding: 12px 16px;
        border-radius: 6px;
        font-family: system-ui,-apple-system,Segoe UI,Roboto,Arial;
      }
      .cl-pwweak {
        background: #FFF4E5;
        border: 1px solid #F7C77E;
        color: #7A3E00;
      }
      .cl-pwsuccess {
        background: #E6FFED;
        border: 1px solid #B7EB8F;
        color: #064420;
      }
    </style>
    <script id="cl-reset-handler"
            data-no-optimize="1" data-no-defer="1" data-no-minify="1">
    (function(){
      // Utilidades
      function q(sel, ctx){ return (ctx || document).querySelector(sel); }
      function getForm(el){ while (el && el.tagName !== 'FORM') { el = el.parentNode; } return el; }
      function findFirstPassword(form){
        var el = q('input[type="password"]', form);
        return el ? (el.value || '').trim() : '';
      }
      function findSecondPassword(form){
        var els = form.querySelectorAll('input[type="password"]');
        if (els.length > 1) { return (els[1].value || '').trim(); }
        var names = ['pass2','password_confirmation','new_password_confirmation'];
        for (var i=0; i<names.length; i++){
          var el = q('[name="'+names[i]+'"]', form);
          if (el) return el.value.trim();
        }
        return '';
      }
      function getVal(names, form){
        for (var i=0; i<names.length; i++){
          var el = q('[name="'+names[i]+'"]', form);
          if (el && typeof el.value === 'string') return el.value.trim();
        }
        return '';
      }
      function ensureErrorBox(form){
        var box = q('.cl-pwweak', form);
        if (!box){
          box = document.createElement('div');
          box.className = 'cl-pwweak';
          form.insertBefore(box, form.firstChild);
        }
        return box;
      }
      function ensureSuccessBox(form){
        var box = q('.cl-pwsuccess', form);
        if (!box){
          box = document.createElement('div');
          box.className = 'cl-pwsuccess';
          form.insertBefore(box, form.firstChild);
        }
        return box;
      }
      function testWeak(pass, login, emailLocal){
        if (pass.length < 8) return 'Debe tener al menos 8 caracteres.';
        if (!/[a-z]/.test(pass)) return 'Incluye al menos una letra minúscula.';
        if (!/[A-Z]/.test(pass)) return 'Incluye al menos una letra MAYÚSCULA.';
        if (!/\d/.test(pass))    return 'Incluye al menos un número.';
        if (!/\W/.test(pass))    return 'Incluye al menos un símbolo (p. ej. !@#$%).';
        if (login && pass.toLowerCase().includes(login.toLowerCase())) return 'No uses tu nombre de usuario dentro de la contraseña.';
        if (emailLocal && pass.toLowerCase().includes(emailLocal.toLowerCase())) return 'No uses tu email dentro de la contraseña.';
        return '';
      }
      // Manejador de envío (click o submit)
      function validateAndBlock(form, e){
        if (!form || !form.querySelector('input[type="password"]')) return false;

        var pass1 = findFirstPassword(form) || getVal(['pass1','password','new_password'], form);
        if (!pass1) return false;

        var pass2 = findSecondPassword(form);
        var login = getVal(['rp_login','user_login','login'], form);
        var email = getVal(['user_email','email'], form);
        var emailLocal = (email && email.indexOf('@') > 0) ? email.split('@')[0] : '';

        // Mismatch
        if (pass2 && pass1 !== pass2) {
          e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          var n = ensureErrorBox(form);
          n.textContent = 'Las contraseñas no coinciden.';
          // Elimina cualquier éxito previo
          var s = q('.cl-pwsuccess', form); if (s) s.remove();
          form.scrollIntoView({behavior:'smooth', block:'start'});
          return true;
        }

        var r = testWeak(pass1, login, emailLocal);
        if (r) {
          e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation();
          var box = ensureErrorBox(form);
          box.textContent = 'La contraseña es demasiado débil. ' + r + ' Debe incluir MAYÚSCULAS, minúsculas, números y un símbolo.';
          var s2 = q('.cl-pwsuccess', form); if (s2) s2.remove();
          form.scrollIntoView({behavior:'smooth', block:'start'});
          return true;
        }
        return false;
      }
      // Inserta aviso de éxito si #pw_success está en la URL
      function showSuccessOnLoad(){
        if (location.hash === '#pw_success') {
          var form = q('.tutor-reset-password-form') || q('.tutor-forgot-password-form') || q('.lost_reset_password');
          if (form) {
            var sBox = ensureSuccessBox(form);
            sBox.textContent = 'Tu contraseña se ha cambiado correctamente. Ya puedes iniciar sesión.';
          }
          history.replaceState({}, document.title, location.href.replace(/#pw_success$/, ''));
        }
      }
      // Engancha eventos en CAPTURA para que corran antes que scripts diferidos
      document.addEventListener('submit', function(e){
        var f = getForm(e.target);
        if (f) validateAndBlock(f, e);
      }, true);
      document.addEventListener('click', function(e){
        var btn = e.target.closest && e.target.closest('button[type="submit"], input[type="submit"]');
        if (!btn) return;
        var f = btn.form || getForm(e.target);
        if (f) validateAndBlock(f, e);
      }, true);
      // Muestra éxito al cargar si existe #pw_success
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showSuccessOnLoad);
      } else {
        showSuccessOnLoad();
      }
    })();
    </script>
    <?php
}, 0 );

// === Redirección tras restablecer: vuelve a la misma página con #pw_success ===
add_action( 'password_reset', function( $user, $new_pass ) {
    // Marcamos que se ha completado el reset
    $GLOBALS['cl_pw_success_trigger'] = true;
}, 10, 2 );

add_filter( 'wp_redirect', function( $location, $status ) {
    if ( ! empty( $GLOBALS['cl_pw_success_trigger'] ) ) {
        // Envía siempre a la URL del reset con hash #pw_success (ajusta el slug si es distinto)
        return home_url( '/dashboard/retrieve-password/#pw_success' );
    }
    return $location;
}, 10, 2 );


// Redirigir al dashboard y forzar refresh tras login
add_action('wp_login', function($user_login, $user) {
    if (in_array('subscriber', (array) $user->roles) || in_array('student', (array) $user->roles)) {
        wp_safe_redirect(home_url('/dashboard/?refresh=1'));
        exit;
    }
}, 10, 2);



/*
Plugin Name: Tutor LMS OTP Message Override (ES)
Description: Traduce y unifica el mensaje y el correo OTP de Tutor LMS al español.
Author: Borja V
Version: 1.1
*/

/**
 * Cambia el mensaje en pantalla (login OTP)
 */


add_action('template_redirect', function () {
    // Solo actuamos en la página de 2FA de Tutor
    if (isset($_GET['step']) && $_GET['step'] === 'tutor-2fa') {

        ob_start(function ($html) {
            // Buscamos el texto original y lo reemplazamos por el nuestro
            $pattern = '/We have sent an e\-mail to your registered e\-mail address.*Please collect OTP and enter here to complete login process\./';

            $replacement = 'Hemos enviado un código de verificación (OTP) a tu correo electrónico registrado. Revisa tu bandeja de entrada e introduce el código.';

            return preg_replace($pattern, $replacement, $html);
        });
    }
});



/*
Plugin Name: Tutor LMS OTP Email Translator (ES)
Description: Traduce el correo de verificación OTP de Tutor LMS al español.
Author: Borja V
Version: 1.0
*/

add_filter('wp_mail', function($args){
    $subject = $args['subject'] ?? '';
    $message = $args['message'] ?? '';

    // Detectar correo OTP
    $is_otp = (stripos($subject, 'OTP') !== false)
           || (strpos($message, 'tutor-email-heading') !== false && stripos($message, 'OTP') !== false)
           || (stripos($message, 'One Time Password') !== false)
           || (stripos($message, 'Login OTP') !== false);

    if (!$is_otp || empty($message)) return $args;

    // Asunto en español
    $args['subject'] = 'Tu código de verificación (OTP)';

    // Traducciones del cuerpo
    $map = [
        'Login OTP' => 'Código de acceso',
        'Your OTP Code' => 'Tu código de verificación',
        'One Time Password (OTP)' => 'Código de verificación (OTP)',
        'Please use the following OTP to complete your login:' =>
            'Por favor, utiliza el siguiente código para completar tu acceso:',
        'Please use the following OTP code to complete your login.' =>
            'Por favor, utiliza el siguiente código para completar tu acceso.',
        'If you did not request this, you can safely ignore this email.' =>
            'Si no solicitaste este acceso, puedes ignorar este correo.',
        'This is an automatically generated email. Please do not reply to this email.' =>
            'Este es un correo automático. Por favor, no respondas a este mensaje.'
    ];

    $translated = $message;
    foreach ($map as $en => $es) {
        $translated = str_ireplace($en, $es, $translated);
    }

    // “Hi Nombre,” → “Hola Nombre,”
    $translated = preg_replace('/>(\s*)Hi\s+([^,<]+),(\s*)</i', '>$1Hola $2,$3<', $translated);

    // Forzar HTML + UTF-8
    $headers = $args['headers'] ?? [];
    if (is_string($headers)) { $headers = [$headers]; }
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $args['message'] = $translated;
    $args['headers'] = $headers;

    return $args;
}, 51);


/*
Plugin Name: Tutor LMS Perfil - Aviso Nombre Real
Description: Muestra un aviso en el perfil de usuario para que introduzcan nombre y apellidos reales.
Author: Atalanta Academy
*/

add_action('wp_footer', function () {
    // Solo ejecuta si estamos en el dashboard de Tutor LMS
    if (is_page() && strpos($_SERVER['REQUEST_URI'], '/dashboard/settings') !== false) {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const nombreField = document.querySelector('input[name="first_name"]');
            if (nombreField) {
                const aviso = document.createElement("div");
                aviso.innerHTML = "⚠️ <b>Introduce tu nombre y apellidos reales</b> para que aparezcan correctamente en tu <b>diploma oficial</b>.";
                aviso.style.background = "#fff4e5";
                aviso.style.border = "1px solid #ffa500";
                aviso.style.padding = "10px";
                aviso.style.marginBottom = "15px";
                aviso.style.borderRadius = "6px";
                aviso.style.color = "#333";
                aviso.style.fontWeight = "bold";

                // Insertar justo antes del campo "Nombre"
                nombreField.closest('.tutor-col-12').insertAdjacentElement('beforebegin', aviso);
            }
        });
        </script>
        <?php
    }
});





/*
Plugin Name: Conversor USD a EUR (ECB XML)
Description: Convierte precios de USD a EUR con el tipo oficial del Banco Central Europeo, sin API Key.
Author: Atalanta Academy
*/

function atalanta_is_certificaciones_offsec_page() {
    return cl_uri_has('/certificaciones-offsec-espanol/');
}

function atalanta_get_usd_rate_from_ecb() {
    $cache_key = 'atalanta_ecb_usd_rate';

    $cached_rate = get_transient($cache_key);
    if ($cached_rate !== false) {
        return (float) $cached_rate;
    }

    $backup_rate = get_option($cache_key);

    if (!atalanta_is_certificaciones_offsec_page()) {
        return $backup_rate ? (float) $backup_rate : new WP_Error(
            'ecb_out_of_scope',
            'Error: la conversión USD/EUR solo está disponible en la página de Certificaciones OffSec.'
        );
    }

    $response = wp_remote_get(
        'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
        array(
            'timeout' => 6,
        )
    );

    if (is_wp_error($response)) {
        return $backup_rate ? (float) $backup_rate : $response;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        return $backup_rate ? (float) $backup_rate : new WP_Error('ecb_empty_body', 'Error: el BCE no devolvió datos.');
    }

    $xml = simplexml_load_string($body);
    if (!$xml) {
        return $backup_rate ? (float) $backup_rate : new WP_Error('ecb_invalid_xml', 'Error: no se pudo procesar el XML del BCE.');
    }

    $rate = null;
    foreach ($xml->Cube->Cube->Cube as $cube) {
        if ((string) $cube['currency'] === 'USD') {
            $rate = (float) $cube['rate'];
            break;
        }
    }

    if (!$rate) {
        return $backup_rate ? (float) $backup_rate : new WP_Error('ecb_missing_rate', 'Error: no se encontró la tasa USD en el BCE.');
    }

    set_transient($cache_key, $rate, 12 * HOUR_IN_SECONDS);
    update_option($cache_key, $rate);

    return $rate;
}

function atalanta_usd_to_eur($atts) {
    $atts = shortcode_atts(array(
        'usd' => 0,
    ), $atts);

    $usd = floatval($atts['usd']);
    if ($usd <= 0) return 'Error: cantidad inválida.';

    if (!atalanta_is_certificaciones_offsec_page()) {
        return '';
    }

    $rate = atalanta_get_usd_rate_from_ecb();
    if (is_wp_error($rate)) {
        return 'Error al obtener la tasa USD/EUR: ' . $rate->get_error_message();
    }

    $eur_value = round($usd / $rate);

    return number_format($eur_value, 0, ',', '.') . ' €';
}
add_shortcode('usd_to_eur', 'atalanta_usd_to_eur');

/**
 * Plugin Name: Atalanta Timeline Popup Fix
 * Description: Activa popups en todos los timelines de la web.
 * Author: Atalanta Academy
 * Version: 1.0
 */

add_action('wp_footer', function(){
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
      document.querySelectorAll(".twae-story").forEach(function(story) {
        story.addEventListener("click", function() {
          const popup = story.querySelector(".twae-popup-content");
          if(popup && popup.innerHTML.trim() !== "") {
            showTimelinePopup(popup.innerHTML);
          }
        });
      });

      function showTimelinePopup(content){
        let modal = document.getElementById("custom-timeline-modal");
        if(!modal){
          modal = document.createElement("div");
          modal.id = "custom-timeline-modal";
          modal.innerHTML = `
            <div class="timeline-overlay"></div>
            <div class="timeline-box">
              <span class="timeline-close">&times;</span>
              <div class="timeline-content"></div>
            </div>`;
          document.body.appendChild(modal);

          modal.querySelector(".timeline-overlay").onclick =
          modal.querySelector(".timeline-close").onclick = function(){
            modal.style.display = "none";
          };
        }
        modal.querySelector(".timeline-content").innerHTML = content;
        modal.style.display = "block";
      }
    });
    </script>

    <style>
    #custom-timeline-modal {
      display:none; position:fixed; top:0; left:0;
      width:100%; height:100%; z-index:9999;
    }
    #custom-timeline-modal .timeline-overlay {
      position:absolute; top:0; left:0; width:100%; height:100%;
      background:rgba(0,0,0,.7);
    }
    #custom-timeline-modal .timeline-box {
      position:relative; max-width:600px;
      margin:5% auto; background:#fff;
      padding:20px; border-radius:12px;
      font-family:"Lato",sans-serif;
    }
    #custom-timeline-modal .timeline-close {
      position:absolute; right:15px; top:10px;
      font-size:26px; cursor:pointer;
      color:#E51432; /* rojo Atalanta */
    }
    </style>
    <?php
});

/**
 * Atalanta — Cambiar "Pregunta y respuesta" por "Certificados"
 * y apuntar a /dashboard/enrolled-courses/completed-courses/
 */
function atalanta_replace_qna_tab($items) {
    $targets = array('question-answer', 'q_and_a', 'question_answer');

    foreach ($targets as $key) {
        if (isset($items[$key])) {

            // Objeto u array según versión
            if (is_object($items[$key])) {
                $items[$key]->title = __('Certificados', 'tutor');
                $items[$key]->url   = site_url('/dashboard/enrolled-courses/completed-courses/');
                $items[$key]->icon  = 'tutor-icon-certificate-landscape'; // icono de certificado
            } elseif (is_array($items[$key])) {
                $items[$key]['title'] = __('Certificados', 'tutor');
                $items[$key]['url']   = site_url('/dashboard/enrolled-courses/completed-courses/');
                $items[$key]['icon']  = 'tutor-icon-certificate-landscape';
            }
        }
    }

    return $items;
}

// Engancha en varios filtros por compatibilidad entre versiones/temas
add_filter('tutor_dashboard/nav_items', 'atalanta_replace_qna_tab', 999);
add_filter('tutor_dashboard_student_nav_items', 'atalanta_replace_qna_tab', 999);
add_filter('tutor_dashboard/instructor_nav_items', 'atalanta_replace_qna_tab', 999);


/*
    *Mover el campo “Mostrar el nombre públicamente como” arriba de Nombre/Apellido.
    *Ocultar el texto por defecto de Tutor LMS (“El nombre a mostrar se visualiza en todos los campos públicos…”).
    *Añadir nuestro aviso en rojo corporativo.
*/

add_action('wp_footer', function () {
    if ( isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/dashboard/settings') !== false ) : ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // --- Seleccionar campos ---
    var displaySelect = document.querySelector('.tutor-form-select[name="display_name"]');
    var firstNameRow  = document.querySelector('input[name="first_name"]')?.closest('.tutor-col-12');
    var lastNameRow   = document.querySelector('input[name="last_name"]')?.closest('.tutor-col-12');
    if (!displaySelect || !firstNameRow || !lastNameRow) return;

    var displayRow = displaySelect.closest('.tutor-col-12');

    // --- Poner Nombre y Apellido en la misma fila ---
    var parentRow = firstNameRow.parentNode;
    parentRow.style.display = "flex";
    parentRow.style.gap = "20px";
    firstNameRow.style.flex = "1";
    lastNameRow.style.flex  = "1";

    // --- Mover "Nombre a mostrar en el certificado" debajo de Apellidos ---
    lastNameRow.parentNode.insertBefore(displayRow, lastNameRow.nextSibling);

    // Cambiar el título del label
    var label = displayRow.querySelector('label');
    if (label) {
        label.textContent = "Nombre a mostrar en el certificado";
    }

    // Ocultar texto por defecto
    var defaultMsg = displayRow.querySelector('.tutor-fs-7.tutor-color-secondary.tutor-mt-12');
    if (defaultMsg) defaultMsg.style.display = "none";

    // Añadir aviso personalizado
    var notice = document.createElement('div');
    notice.style.cssText = "margin-top:8px;font-size:13px;color:#000;font-weight:500;";
    notice.innerHTML = "⚠️ Este campo define cómo aparecerá tu nombre en el <b>certificado</b>.";
    displayRow.appendChild(notice);
});
</script>
<?php endif;
});
