<?php

/*
 *  IdPRef - IdP de Referencia para SIR 2 basado en SimpleSAMLPHP v1.13.1
 * =========================================================================== *
 *
 * Copyright (C) 2014 - 2015 by the Spanish Research and Academic Network.
 * This code was developed by Auditoria y Consultoría de Privacidad y Seguridad
 * (PRiSE http://www.prise.es) for the RedIRIS SIR service (SIR: 
 * http://www.rediris.es/sir)
 *
 * *****************************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not
 * use this file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations under
 * the License.
 *
 * ************************************************************************** */

/**
 * Paso 7 del modulo instalador para SimpleSAMLphp v1.13.1
 * @package    IdPRef\modules\idpinstaller
 * @author     "PRiSE [Auditoria y Consultoria de privacidad y Seguridad, S.L.]"
 * @copyright  Copyright (C) 2014 - 2015 by the Spanish Research and Academic
 *             Network
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version    0.3-Sprint3-R57
 */

/**
 * Hook a ejecutar antes del paso 7 de la instalaci√≥n
 *
 * @param array &$data  Los datos a utilizar por las plantillas de tipo stepn
 */
function idpinstaller_hook_step7(&$data) {
    $hostname = $_SERVER['HTTP_HOST'];
    $pkey_file = $hostname . ".key.pem";
    $cert_file = $hostname . ".crt.pem";
    $new_certificate = (isset($_REQUEST['organization_name']) && !empty($_REQUEST['organization_name']));
    $old_certificate = (isset($_REQUEST['private_key']) && !empty($_REQUEST['private_key']) && isset($_REQUEST['certificate']) && !empty($_REQUEST['certificate']));
    if ($new_certificate || $old_certificate) {
        $dir_certs = realpath(__DIR__ . "/../../../") . "/cert";
        $org_name = $_REQUEST['organization_name'];
        $crt = $dir_certs . "/" . $cert_file;
        $pem = $dir_certs . "/" . $pkey_file;

        if (!file_exists($dir_certs) && !is_writable($dir_certs . "/../")) {
            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_mkdir_cert_error}');
            $data['errors'][] = "<pre>&gt; mkdir $dir_certs</pre>";
            return true;
        } else if (!is_dir($dir_certs)) {
            exec("mkdir $dir_certs");
        } else if (!is_writable($dir_certs)) {
            $username = getFileUsername($dir_certs);
            $groupname = getApacheGroup();
            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_perm_cert_error}');
            $data['errors'][] = "<pre>&gt; chown -R " . $username . ":" . $groupname . " $dir_certs\n&gt; chmod -R g+w " . $dir_certs . "</pre>";
            return true;
        }
        if ($old_certificate) {
            $priv_key = $_REQUEST['certificate'];
            $cert = $_REQUEST['private_key'];
            if (openssl_x509_check_private_key($cert, $priv_key)) {
                $a1 = openssl_pkey_get_public($cert);
                if ($a1 != null) {
                    $details = openssl_pkey_get_details($a1);
                    if (is_array($details) && array_key_exists("type", $details) && $details["type"] == OPENSSL_KEYTYPE_RSA) {
                        $x = @file_put_contents($pem, $priv_key);
                        $y = @file_put_contents($crt, $cert);
                        if ($x === false || $y === false) {
                            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_error}');
                            return true;
                        }
                    } else {
                        $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_rsa_error}');
                        return true;
                    }
                } else {
                    $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_rsa_error}');
                    return true;
                }
            } else {
                $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_error_comparation}');
                return true;
            }
        } else {
            $o_name = str_replace(" ", "\ ", $org_name);
            $dir_script = realpath(__DIR__) . "/../lib/makeCert.sh";
            //exec("sh $dir_script $dir_certs/$cert_file $pkey_file $o_name $hostname");

            /* Este tratamiento es un poco confuso, pero es la mejor de
             * invocar una  llamada a sistema sin perder el control en PHP
             */
            $cmdToExecute = $dir_script; // Ruta al shell script
            $cmdToExecute .= ' ' . $dir_certs . '/' . $cert_file; // Ruta absoluta para el certificado a generar
            $cmdToExecute .= ' ' . $dir_certs . '/' . $pkey_file; // Ruta absoluta para la clave privada a generar
            $cmdToExecute .= ' ' . $o_name; // Nombre de la organización, a utilizar en el RDN O
            $cmdToExecute .= ' ' . $hostname; // Nombre del host a utilizar en los RDN DC y CN
            //list($respStdout, $respStderr, $outCode) = execInShell($cmdToExecute, NULL); // Si, el uso del constructor del leguaje list() es un aguarrería pero queda muy descriptivo
            $result = array();
            $result = execInShell($cmdToExecute, NULL);
            $respStdout = $result[0]; // <= STDOUT
            $respStderr = $result[1]; // <= STDERR
            $outCode    = $result[2]; // <= Out Code

            // Se procesan los mensajes de salida de errores
            if($outCode !== 0){
                $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_command_error}').'</br></br><pre>'.$respStderr.'</pre>';
                }
        }

        if (!file_exists($crt) || !file_exists($pem)) {
            $username = getFileUsername($dir_certs);
            $groupname = getApacheGroup();
            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_error}');
            if (isset($command)) {
                $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_cert_error_suggest}');
                $data['errors'][] = $command;
                return true;
            }
        }

        $filename = __DIR__ . '/../../../config/config.php';
        include($filename);
        $config['metadata.sign.enable'] = TRUE;
        $config['metadata.sign.privatekey'] = $pkey_file;
        $config['metadata.sign.privatekey_pass'] = NULL;
        $config['metadata.sign.certificate'] = $cert_file;
        $res = @file_put_contents($filename, '<?php  $config = ' . var_export($config, 1) . "; ?>");
        if (!$res) {
            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step2_contact_save_error}');
            $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step2_contact_save_error2}') . " <i>" . realpath($filename) . "</i>";
            return true;
        }
    } else if (!isset($_REQUEST['only_part2']) || empty($_REQUEST['only_part2'])) {
        $data['errors'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step6_org_name_empty_error}');
        return true;
    }
    //Segunda parte del Hook7
    $filename_auths = realpath(__DIR__ . '/../../../config/authsources.php');
    include ($filename_auths);
    $filename_hosted = realpath(__DIR__ . '/../../../metadata/saml20-idp-hosted.php');
    $perms_ko = array();
    
    $file_tmp_name = realpath(__DIR__ . '/../../../cert/').'/tmp_org_info.php';
    if(file_exists($file_tmp_name)){
        include ($file_tmp_name);
    }
    $org_name = !empty($org_info['name']) && $org_info['name'] !== '' ? $org_info['name'] : "idp-$hostname";
    $org_desc = !empty($org_info['info']) && $org_info['info'] !== '' ? $org_info['info'] : "idp-$hostname";
    $org_url  = !empty($org_info['url'])  && $org_info['url']  !== '' ? $org_info['url']  : $hostname;
    
    if (!is_writable($filename_hosted) || !is_readable($filename_hosted)) {
        array_push($perms_ko, $filename_hosted);
    }
    if (array_key_exists('sql_datasource', $config)) {
        $auth = 'sql_datasource';
    } else if (array_key_exists('ldap_datasource', $config)) {
        $auth = 'ldap_datasource';
    }

    $m = "<?php\n\n\$metadata['idp-$hostname'] = array(
        'UIInfo' => array(
            'DisplayName' => array(
                'en' => '$org_name ',
                'es' => '$org_name ',
                'gl' => '$org_name ',
                'eu' => '$org_name ',
                'ca' => '$org_name ',
            ),
            'Description' => array(
                'en' => '$org_desc',
                'es' => '$org_desc',
                'gl' => '$org_desc',
                'eu' => '$org_desc',
                'ca' => '$org_desc',
            ),
            'InformationURL' => array(
                'en' => '$org_url',
                'es' => '$org_url',
                'gl' => '$org_url',
                'eu' => '$org_url',
                'ca' => '$org_url',
            ),
        ),
        'host' => '__DEFAULT__',
        'privatekey' => '$pkey_file',
        'certificate' => '$cert_file',
        'auth' => '$auth',
        'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
        'attributes' => array(
                'eduPersonTargetedID',
                'eduPersonAffiliation',
                'schacHomeOrganization',
                'eduPersonEntitlement',
                'schacPersonalUniqueCode',
                'uid',
                'mail',
                'displayName',
                'commonName',
                'eduPersonScopedAffiliation',
                'eduPersonPrincipalName',
                'schacHomeOrganizationType',
        ), 
        'authproc' => array(
            100 => array('class' => 'core:AttributeMap', 'name2oid'),
        ),
        'assertion.encryption' => true
    );";

    $res = @file_put_contents($filename_hosted, $m);
    unlink($file_tmp_name);
    if (!$res || count($perms_ko) > 0) {
        if (function_exists('posix_getgrnam')) {
            $aux = "<br/>" . $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_error}');
            $aux .= "<br/>" . $data['ssphpobj']->t('{idpinstaller:idpinstaller:step4_perms_ko}');
            $filename = $perms_ko[0];
            $recursive = is_dir($filename) ? "-R" : "";
            $aux.= "<pre>&gt; chown $recursive " . getFileUsername($filename) . ":" . getApacheGroup() . " $filename\n&gt; chmod $recursive g+rw " . $filename . "</pre>";
        }
        $data['errors2'][] = $aux;
        $data['errors2'][] = $data['ssphpobj']->t("{idpinstaller:idpinstaller:step1_remember_change_perms}");
    }
    if (count($data['errors2']) == 0) {
        $url_meta = 'http://' . $_SERVER['HTTP_HOST'] . substr($_SERVER['SCRIPT_NAME'], 0, -24) . "saml2/idp/metadata.php?output=xhtml";
        $data['info'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_all_ok}');
        $data['info'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_all_info_extra}') . " <a href='$url_meta' target='_blank'>" . $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_here}') . "</a>";
    } else {
        $data['errors2'][] = $data['ssphpobj']->t('{idpinstaller:idpinstaller:step7_error}');
    }
    return $res;
}

/**
 * Ejecuta un comando o llamada al sistema sin perder demasiado control desde
 * PHP.
 * @param type $cmd Comando a ejecutar
 * @param type $path El directorio inicial de trabajo para el comando. Este debe
 *                   ser una ruta absoluta, o si se prefiere puede ser NULL si
 *                   se desea usar el valor por defecto (entonces será el
 *                   directorio de trabajo del proceso PHP en curso)
 * @return array Vector con la salida estandar en el primer elemento del mismo y
 *               la salida de errores en el segundo elemento.
 */
function execInShell($cmd, $path) {
    $res = array();
    $pipes = array();
    $descriptorspec = array(
        0 => array("pipe", "r"), // stdin is a pipe that the child will read from
        1 => array("pipe", "w"), // stdout is a pipe that the child will write to
        2 => array("pipe", "w")   // stderr is a pipe that the child will write to
    );
    $process = proc_open($cmd, $descriptorspec, $pipes, $path, null);
    if ($process !== false && is_resource($process)) {
        // $pipes:
        // 1 => readable handle connected to child stdout
        // 2 => readable handle connected to child stderr
        $res[0] = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $res[1] = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $res[2] = proc_close($process);
    }
    return $res;
}
