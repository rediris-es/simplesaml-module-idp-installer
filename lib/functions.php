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
 * 
 * @package    IdPRef\modules\idpinstaller\lib
 * @author     "PRiSE [Auditoria y Consultoria de privacidad y Seguridad, S.L.]"
 * @copyright  Copyright (C) 2014 - 2015 by the Spanish Research and Academic
 *             Network
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version    0.3-Sprint3-R57
 */

function isExtensionActive($ext, $loaded_modules) {
    return array_search($ext, $loaded_modules) !== false;
}

function isLDAPextensionActive($loaded_modules) {
    return isExtensionActive("ldap", $loaded_modules);
}

function isPDOextensionActive($loaded_modules) {
    return isExtensionActive("PDO", $loaded_modules);
}

function isPDOModulesActive($loaded_modules) {
    $pdos           = array("pdo_oci", "pdo_mysql", "pdo_sqlite", "pdo_pgsql", "pdo_mssql");
    $active_modules = array();
    for ($index = 0; $index < count($pdos); $index++) {
        if (array_search($pdos[$index], $loaded_modules) !== false) {
            $active_modules[] = $pdos[$index];
        }
    }
    return count($active_modules) > 0;
}

function getPDOModulesActive($loaded_modules) {
    $pdos           = array("pdo_oci", "pdo_mysql", "pdo_sqlite", "pdo_pgsql", "pdo_mssql");
    $active_modules = array();
    for ($index = 0; $index < count($pdos); $index++) {
        if (array_search($pdos[$index], $loaded_modules) !== false) {
            $active_modules[] = $pdos[$index];
        }
    }
    return $active_modules;
}

function drawButton($next_step, $msg, $style = '', $inputs = '') {
    ?>
    <div <?php echo $style; ?>>
        <form action="" method="post">    
            <input type="hidden" name="step" value="<?php echo $next_step; ?>"/>
            <?php echo $inputs; ?>
            <input type="submit" value="<?php echo $msg; ?>"/>
        </form>
    </div>
    <?php
}

function getDataSources() {
    $loaded_extensions = get_loaded_extensions();
    $ldap              = isLDAPextensionActive($loaded_extensions);
    $pdo               = isPDOextensionActive($loaded_extensions);
    $modulos           = $pdo ? isPDOextensionActive($loaded_extensions) : false;
    if ($ldap && $pdo && $modulos) {
        $res = "all";
    } else if (!$ldap && $pdo && $modulos) {
        $res = "pdo";
    } else {
        $res = "ldap";
    }
    return $res;
}

function transaleXMLToSsPHP($xmldata){
    
    if (!empty($xmldata)) {

        SimpleSAML_Utilities::validateXMLDocument($xmldata, 'saml-meta');
        $entities = SimpleSAML_Metadata_SAMLParser::parseDescriptorsString($xmldata);

        /* Get all metadata for the entities. */
        foreach ($entities as &$entity) {
            $entity = array(
                'saml20-sp-remote' => $entity->getMetadata20SP(),
                'saml20-idp-remote' => $entity->getMetadata20IdP(),
            );
        }

        /* Transpose from $entities[entityid][type] to $output[type][entityid]. */
        $output = SimpleSAML_Utilities::transposeArray($entities);

        /* Merge all metadata of each type to a single string which should be
         * added to the corresponding file.
         */
        foreach ($output as $type => &$entities) {

            $text = '';

            foreach ($entities as $entityId => $entityMetadata) {

                if ($entityMetadata === NULL) {
                    continue;
                }

                /* Remove the entityDescriptor element because it is unused, and only
                 * makes the output harder to read.
                 */
                unset($entityMetadata['entityDescriptor']);

                $text .= '$metadata[' . var_export($entityId, TRUE) . '] = ' .
                        var_export($entityMetadata, TRUE) . ";\n";
            }

            $entities = $text;
        }
    } else {
        $output  = array();
    }
    return $output;
}

function generateSecurePass($length = 9, $add_dashes = false, $available_sets = 'luds')
    {
        $sets = array();

        if(strpos($available_sets, 'l') !== false){
            $sets[] = 'abcdefghjkmnpqrstuvwxyz';
        }

        if(strpos($available_sets, 'u') !== false){
            $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        }

        if(strpos($available_sets, 'd') !== false){
            $sets[] = '23456789';
        }

        if(strpos($available_sets, 's') !== false){
            $sets[] = '!@#$%&*?';
        }

        $all = '';
        $password = '';

        foreach($sets as $set){
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }

        $all = str_split($all);

        for($i = 0; $i < $length - count($sets); $i++){
            $password .= $all[array_rand($all)];
        }

        $password = str_shuffle($password);

        if(!$add_dashes){
            return $password;
        }

        $dash_len = floor(sqrt($length));
        $dash_str = '';

        while(strlen($password) > $dash_len){
            $dash_str .= substr($password, 0, $dash_len) . '-';
            $password = substr($password, $dash_len);
        }

        $dash_str .= $password;

        return $dash_str;
    }

