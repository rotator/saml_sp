<?php

/**
 * @file
 * Contains \Drupal\samlsp\Controller\SamlSPController.
 */

namespace Drupal\saml_sp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use OneLogin_Saml2_Settings;
use OneLogin_Saml2_Response;

/**
 * Provides route responses for the SAML SP module
 */
class SamlSPController extends ControllerBase {

  /**
   * generate the XMl metadata for the given IDP
   */
  public function metadata($return_string = FALSE) {
    list($metadata, $errors) = saml_sp__get_metadata();

    $output = $metadata;

    if ($return_string) {
      return $output;
    }
    $response = new Response();
    $response->setContent($metadata);
    $response->headers->set('Content-Type', 'text/xml');
    return $response;
  }

  /**
   * receive data back from the IdP
   */
  public function consume() {
    if (!$this->saml_sp__is_valid_authentication_response()) {
      return new RedirectResponse(\Drupal::url('<front>'));
    }

    // The OneLogin_Saml_Response object uses the settings to verify the validity
    // of a request, in OneLogin_Saml_Response::isValid(), via XMLSecurityDSig.
    // Extract the incoming ID (the `inresponseto` parameter of the
    // `<samlp:response` XML node).

    if ($inbound_id = _saml_sp__extract_inbound_id($_POST['SAMLResponse'])) {
      if ($request = saml_sp__get_tracked_request($inbound_id)) {
        $idp = saml_sp_idp_load($request['idp']);

        // Try to check the validity of the samlResponse.
        try {
          if (!is_array($idp->x509_cert)) {
            $certs = array($idp->x509_cert);
          }
          else {
            $certs = $idp->x509_cert;
          }
          $is_valid = FALSE;
          // go through each cert and see if one of them provides a valid
          // response
          foreach ($certs AS $cert) {
            if ($is_valid) {
              continue;
            }
            $idp->x509_cert = $cert;
            $settings = saml_sp__get_settings($idp);
            // Creating Saml2 Settings object from array
            $saml_settings = new OneLogin_Saml2_Settings($settings);
            //$saml_response = new saml_sp_Response($saml_settings, $_POST['SAMLResponse']);
            $saml_response = new OneLogin_Saml2_Response($saml_settings, $_POST['SAMLResponse']);
            // $saml_response->isValid() will throw various exceptions to communicate
            // any errors. Sadly, these are all of type Exception - no subclassing.
            $is_valid = $saml_response->isValid();
          }
        }
        catch (Exception $e) {
          // @TODO: inspect the Exceptions, and log a meaningful error condition.
          \Drupal::logger('saml_sp')->error('Invalid response, %exception', array('%exception' => $e->message));
          $is_valid = FALSE;
        }
        // Remove the now-expired tracked request.
        \Drupal::cache()->delete($inbound_id);

        if (!$is_valid) {
          $error = $saml_response->getError();
          list($problem) = array_reverse(explode(' ', $error));

          switch ($problem) {
            case 'Responder':
              $message = t('There was a problem with the response from @idp_name. Please try again later.', array('@idp_name' => $idp->name));
              break;
            case 'Requester':
              $message = t('There was an issue with the request made to @idp_name. Please try again later.', array('@idp_name' => $idp->name));
              break;
            case 'VersionMismatch':
              $message = t('SAML VersionMismatch between @idp_name and @site_name. Please try again later.', array('@idp_name' => $idp->name, '@site_name' => variable_get('site_name', 'Drupal')));
              break;
          }
          if (!empty($message)) {
            drupal_set_message($message, 'error');
          }
          \Drupal::logger('saml_sp')->error('Invalid response, @error: <pre>@response</pre>', array('@error' => $error, '@response' => print_r($saml_response, TRUE)));
        }


        // Invoke the callback function.
        $callback = $request['callback'];
        $result = $callback($is_valid, $saml_response, $idp);

        // The callback *should* redirect the user to a valid page.
        // Provide a fail-safe just in case it doesn't.
        if (empty($result)) {
          return new RedirectResponse(\Drupal::url('user.page'));
        }
        else {
          return $result;
        }
      }
      else {
        \Drupal::logger('saml_sp')->error('Request with inbound ID @id not found.', array('@id' => $inbound_id));
      }
    }
    // Failover: redirect to the homepage.
    \Drupal::logger('saml_sp')->warning('Failover: redirect to the homepage. No inbound ID or something.');
    return new RedirectResponse(\Drupal::url('<front>'));
  }

  /**
   * Check that a request is a valid SAML authentication response.
   *
   * @return Boolean
   */
  private function saml_sp__is_valid_authentication_response() {
    return ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['SAMLResponse']));
  }

  /**
   * log the user out
   */
  public function logout() {

  }
}
