<?php
namespace Drupal\saml_sp\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use OneLogin_Saml2_Utils;

class SamlSpSubscriber implements EventSubscriberInterface {

  public function checkForCertExpiration(GetResponseEvent $event) {
    $config = \Drupal::config('saml_sp.settings');
    $user = \Drupal::currentUser();
    if ($user->hasPermission('configure saml sp') &&
      function_exists('openssl_x509_parse') &&
      !empty($config->get('cert_location')) &&
      file_exists($config->get('cert_location'))
    ) {
      $encoded_cert = trim(file_get_contents($config->get('cert_location')));
      $cert = openssl_x509_parse(OneLogin_Saml2_Utils::formatCert($encoded_cert));
      $test_time = REQUEST_TIME;
      if ($cert['validTo_time_t'] < $test_time) {
        $markup = new TranslatableMarkup('Your site\'s SAML certificate is expired. Please replace it with another certificate and request an update to your Relying Party Trust (RTP). You can enter in a location for the new certificate/key pair on the <a href="@url">SAML Service Providers</a> page. Until the certificate/key pair is replaced your SAML authentication service will not function.'
          , array(
            '@url' =>  \Drupal::url('saml_sp.admin'),
          )
        );
        drupal_set_message($markup, 'error', FALSE);
      }
      else if (($cert['validTo_time_t'] - $test_time) < (60 * 60 * 24 * 30)) {
        $markup = new TranslatableMarkup('Your site\'s SAML certificate will expire in %interval. Please replace it with another certificate and request an update to your Relying Party Trust (RTP). You can enter in a location for the new certificate/key pair on the <a href="@url">SAML Service Providers</a> page. Failure to update this certificate and update the Relying Party Trust (RTP) will result in the SAML authentication service not working.',
          array(
            '%interval' => \Drupal::service('date.formatter')->formatInterval($cert['validTo_time_t'] - $test_time, 2),
            '@url' => \Drupal::url('saml_sp.admin'),
          )
        );
        drupal_set_message($markup,'warning', FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForCertExpiration');
    return $events;
  }
}