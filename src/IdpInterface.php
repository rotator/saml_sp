<?php

namespace Drupal\saml_sp;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Example entity.
 */
interface IdpInterface extends ConfigEntityInterface {
  public function label();
  public function setLabel($label);
  public function id();
  public function setId($id);
  public function entity_id();
  public function setEntity_id($entity_id);
  public function app_name();
  public function setApp_name($app_name);
  public function nameid_field();
  public function setNameid_field($nameid_field);
  public function login_url();
  public function setLogin_url($login_url);
  public function logout_url();
  public function setLogout_url($logout_url);
  public function x509_cert();
  public function setX509_cert($x509_cert);
  public function authn_context_class_ref();
  public function setAuthn_context_class_ref($authn_context_class_ref);
}
