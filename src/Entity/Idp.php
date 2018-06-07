<?php

namespace Drupal\saml_sp\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\saml_sp\IdpInterface;

/**
 * Defines the Idp entity.
 *
 * @ConfigEntityType(
 *   id = "idp",
 *   label = @Translation("Identity Provider"),
 *   handlers = {
 *     "list_builder" = "Drupal\saml_sp\Controller\IdpListBuilder",
 *     "form" = {
 *       "add" = "Drupal\saml_sp\Form\IdpForm",
 *       "edit" = "Drupal\saml_sp\Form\IdpForm",
 *       "delete" = "Drupal\saml_sp\Form\IdpDeleteForm",
 *     }
 *   },
 *   config_prefix = "idp",
 *   admin_permission = "configure saml sp",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/people/saml_sp/idp/edit/{idp}",
 *     "delete-form" = "/admin/config/people/saml_sp/idp/delete/{idp}",
 *   }
 * )
 */
class Idp extends ConfigEntityBase implements IdpInterface {

  public $id;
  public $label;
  public $entity_id;
  public $app_name;
  public $nameid_field;
  public $login_url;
  public $logout_url;
  public $x509_cert;
  public $authn_context_class_ref;

  public function __construct(array $values = array(), $entity_type = 'idp') {
    return parent::__construct($values, $entity_type);
  }

  public function label() {
    return $this->label;
  }
  public function setLabel($label) {
    $this->label = $label;
  }

  public function id() {
    return $this->id;
  }
  public function setId($id) {
    $this->id = $id;
  }
  public function entity_id() {
    return $this->entity_id;
  }
  public function setEntity_id($entity_id) {
    $this->entity_id = $entity_id;
  }
  public function app_name() {
    return $this->app_name;
  }
  public function setApp_name($app_name) {
    $this->app_name = $app_name;
  }
  public function nameid_field() {
    return $this->nameid_field;
  }
  public function setNameid_field($nameid_field) {
    $this->nameid_field = $nameid_field;
  }
  public function login_url() {
    return $this->login_url;
  }
  public function setLogin_url($login_url) {
    $this->login_url = $login_url;
  }
  public function logout_url() {
    return $this->logout_url;
  }
  public function setLogout_url($logout_url) {
    $this->logout_url = $logout_url;
  }
  public function x509_cert() {
    return $this->x509_cert;
  }
  public function setX509_cert($x509_certs) {
    if (isset($x509_certs['actions'])) {
      unset($x509_certs['actions']);
    }
    $this->x509_cert = $x509_certs;
  }
  public function authn_context_class_ref() {
    return $this->authn_context_class_ref;
  }
  public function setAuthn_context_class_ref($authn_context_class_ref) {
    $array = array();
    foreach ($authn_context_class_ref AS $value) {
      if ($value) {
        $array[$value] = $value;
      }
    }
    $this->authn_context_class_ref = $array;
  }
}
