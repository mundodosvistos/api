<?php
/*
Plugin Name: Mundo dos Vistos API
Description: Plugin para interagir com a API Mundo dos Vistos e exibir conteúdo no site.
Version: 1.2
Author: Mundo dos Vistos
*/

// Classe para gerenciar atualizações automáticas do plugin
if (!class_exists('MundoDosVistos_API_Updater')) {

    class MundoDosVistos_API_Updater {

        private $file;
        private $plugin;
        private $basename;
        private $active;

        public function __construct($file) {
            $this->file = $file;
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
            add_filter('plugins_api', array($this, 'plugin_api_call'), 10, 3);
        }

        public function check_for_update($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }

            // Informações do plugin
            $repo = 'https://api.github.com/repos/SEU_USUARIO/SEU_REPOSITORIO/releases/latest';
            $response = wp_remote_get($repo);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response));

                if (isset($data->tag_name)) {
                    $new_version = $data->tag_name;
                    $plugin_version = $transient->checked[$this->basename];

                    if (version_compare($plugin_version, $new_version, '<')) {
                        $plugin_info = array(
                            'slug' => $this->basename,
                            'new_version' => $new_version,
                            'url' => $data->html_url,
                            'package' => $data->assets[0]->browser_download_url,
                        );

                        $transient->response[$this->basename] = (object) $plugin_info;
                    }
                }
            }
            return $transient;
        }

        public function plugin_api_call($res, $action, $args) {
            if ($action !== 'plugin_information' || $args->slug !== $this->basename) {
                return $res;
            }

            $repo = 'https://api.github.com/repos/SEU_USUARIO/SEU_REPOSITORIO';
            $response = wp_remote_get($repo);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response));

                if (isset($data->name)) {
                    $res = (object) array(
                        'name' => $data->name,
                        'slug' => $this->basename,
                        'version' => $data->tag_name,
                        'author' => '<a href="https://github.com/SEU_USUARIO">Seu Nome</a>',
                        'homepage' => $data->html_url,
                        'sections' => array(
                            'description' => $data->description,
                        ),
                        'download_link' => $data->html_url,
                    );
                }
            }

            return $res;
        }
    }

    // Inicializa o updater para o plugin
    if (is_admin()) {
        $plugin_basename = plugin_basename(__FILE__);
        new MundoDosVistos_API_Updater($plugin_basename);
    }
}


// Adiciona o menu no admin
function mdv_add_admin_menu() {
    add_menu_page(
        'Mundo dos Vistos API', 
        'Vistos API', 
        'manage_options', 
        'mdv-api', 
        'mdv_api_page', 
        'dashicons-admin-site', 
        20
    );
    
    add_submenu_page(
        'mdv-api',
        'Configurações da API',
        'Configurações',
        'manage_options',
        'mdv-api-settings',
        'mdv_api_settings_page'
    );
}
add_action('admin_menu', 'mdv_add_admin_menu');

// Página de configurações da API
function mdv_api_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações da API Mundo dos Vistos</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('mdv_api_settings_group');
            do_settings_sections('mdv-api-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Registrar configurações da API
function mdv_register_settings() {
    register_setting('mdv_api_settings_group', 'mdv_api_key');

    add_settings_section(
        'mdv_api_settings_section',
        'Chave API',
        'mdv_api_settings_section_callback',
        'mdv-api-settings'
    );

    add_settings_field(
        'mdv_api_key',
        'Chave API',
        'mdv_api_key_callback',
        'mdv-api-settings',
        'mdv_api_settings_section'
    );
}
add_action('admin_init', 'mdv_register_settings');

function mdv_api_settings_section_callback() {
    echo 'Insira sua chave API para utilizar a API do Mundo dos Vistos.';
}

function mdv_api_key_callback() {
    $api_key = get_option('mdv_api_key');
    echo '<input type="text" name="mdv_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

// Página principal do plugin
function mdv_api_page() {
    ?>
    <div class="wrap">
        <h1>Mundo dos Vistos - API</h1>
        <form method="post" action="">
            <input type="hidden" name="mdv_action" value="listar_paises">
            <input type="submit" value="Listar Países" class="button button-primary">
        </form>
        <form method="post" action="">
            <label for="id_pais">ID do País:</label>
            <input type="text" name="id_pais" id="id_pais">
            <input type="hidden" name="mdv_action" value="listar_categorias">
            <input type="submit" value="Listar Categorias" class="button button-primary">
        </form>
        <form method="post" action="">
            <label for="id_categoria">ID da Categoria:</label>
            <input type="text" name="id_categoria" id="id_categoria">
            <input type="hidden" name="mdv_action" value="listar_documentos">
            <input type="submit" value="Listar Documentos" class="button button-primary">
        </form>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $action = sanitize_text_field($_POST['mdv_action']);

        if ($action == 'listar_paises') {
            $response = mdv_listar_paises();
        } elseif ($action == 'listar_categorias') {
            $id_pais = sanitize_text_field($_POST['id_pais']);
            $response = mdv_listar_categorias($id_pais);
        } elseif ($action == 'listar_documentos') {
            $id_categoria = sanitize_text_field($_POST['id_categoria']);
            $response = mdv_listar_documentos($id_categoria);
        }

        echo '<pre>';
        print_r($response);
        echo '</pre>';
    }
}

// Função para listar países
function mdv_listar_paises() {
    $url = 'https://vis.to/api/';
    $api_key = get_option('mdv_api_key');

    $args = array(
        'body' => array(
            'action' => 'listar_paises'
        ),
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return 'Erro: ' . $response->get_error_message();
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Função para listar categorias
function mdv_listar_categorias($id_pais) {
    $url = 'https://vis.to/api/';
    $api_key = get_option('mdv_api_key');

    $args = array(
        'body' => array(
            'action' => 'listar_categorias',
            'id' => $id_pais
        ),
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return 'Erro: ' . $response->get_error_message();
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Função para listar documentos
function mdv_listar_documentos($id_categoria) {
    $url = 'https://vis.to/api/';
    $api_key = get_option('mdv_api_key');

    $args = array(
        'body' => array(
            'action' => 'listar_documentos',
            'id' => $id_categoria
        ),
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ),
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
        return 'Erro: ' . $response->get_error_message();
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}

// Adicionar Shortcode
function mdv_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'action' => 'listar_paises',
            'id' => ''
        ),
        $atts,
        'mdv_api'
    );

    if ($atts['action'] == 'listar_paises') {
        $response = mdv_listar_paises();
    } elseif ($atts['action'] == 'listar_categorias') {
        $response = mdv_listar_categorias($atts['id']);
    } elseif ($atts['action'] == 'listar_documentos') {
        $response = mdv_listar_documentos($atts['id']);
    }

    // Verifica se há dados na resposta da API
    if (is_array($response) && isset($response['data']['paises'])) {
        $paises = $response['data']['paises'];
        
        // Gera o HTML do select
        $html = '<select name="pais" id="pais">';
        foreach ($paises as $pais) {
            $html .= '<option value="' . esc_attr($pais['idpais']) . '">' . esc_html($pais['pais']) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    return 'Nenhum país disponível.';
}

add_shortcode('mdv_api', 'mdv_shortcode');
