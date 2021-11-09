<?php

namespace Drupal\social_embed\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\url_embed\UrlEmbed;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for Social Embed endpoint handling.
 */
class EmbedController extends ControllerBase {

  /**
   * Url Embed services.
   *
   * @var \Drupal\url_embed\UrlEmbed
   */
  protected UrlEmbed $urlEmbed;

  /**
   * The flood service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected FloodInterface $flood;

  /**
   * The EmbedController constructor.
   *
   * @param \Drupal\url_embed\UrlEmbed $url_embed
   *   The url embed services.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood service.
   */
  public function __construct(UrlEmbed $url_embed, FloodInterface $flood) {
    $this->urlEmbed = $url_embed;
    $this->flood = $flood;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('url_embed'),
      $container->get('flood')
    );
  }

  /**
   * Generates embed content of a give URL.
   *
   * When the site-wide setting for consent is enabled, the links in posts and
   * nodes will be replaced with placeholder divs and a show content button.
   *
   * Once user clicks the button, it will send request to this controller along
   * with url of the content to embed and an uuid which differentiates each
   * link.
   *
   * See:
   * 1. SocialEmbedConvertUrlToEmbedFilter::convertUrls
   * 2. SocialEmbedUrlEmbedFilter::process
   * 3. EmbedConsentForm::buildForm
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax response.
   */
  public function generateEmbed(Request $request) {
    // Get the requested URL of content to embed.
    $url = $request->query->get('url');
    // Get unique identifier for the button which was clicked.
    $uuid = $request->query->get('uuid');

    // If $url or $uuid is not present, then request is malformed.
    if ($url == NULL && Uuid::isValid($uuid) != FALSE) {
      throw new NotFoundHttpException();
    }

    // Let's prepare a unique flood identifier using our uuid.
    // This is to to ensure that the value doesn't exceed 128 characters.
    // As identifiers cannot be more than 128 characters long, otherwise
    // it will cause an error when attempting to write the flood record
    // to the database.
    $hashedValue = Crypt::hashBase64($uuid);
    // The reason we are also using the combination of uuid and client IP is
    // to make sure we do not end up blocking a user who is just exploring our
    // website. There can be a scenario where, this endpoint can be called more
    // than our threshold value due to numerous embedded content on the
    // platform, and if we use only clientIp, we might end up blocking a
    // genuine request.
    $floodIdentifier = $request->getClientIP() . $hashedValue;

    // The maximum number of times each user can do this event per time window.
    $retries = Settings::get('social_embed_retries', 50);
    // Number of seconds in the time window for embed.
    $timeWindow = Settings::get('social_embed_time_window', 3600);

    // Only proceed if this is not a malicous request.
    if (!$this->flood->isAllowed('social_embed.generateembed_flood_event', $retries, $timeWindow, $floodIdentifier)) {
      throw new AccessDeniedHttpException();
    }
    else {
      // Register the flood event in system.
      $this->flood->register('social_embed.generateembed_flood_event', $timeWindow, $floodIdentifier);
      // Use uuid to set the selector to the specific div we need to replace.
      $selector = "#social-embed-iframe-$uuid";
      // If the content is embeddable then return the iFrame.
      $info = $this->urlEmbed->getUrlInfo($url);
      if ($info && !empty($iframe = $info['code'])) {
        $provider = strtolower($info['providerName']);
        $content = "<div id='social-embed-iframe-$uuid' class='social-embed-iframe-$provider'><p>$iframe</p></div>";
      }
      else {
        // Else return the link itself.
        $content = Link::fromTextAndUrl($url, Url::fromUri($url))->toString();
      }
    }

    // Let's prepare the response.
    $response = new AjaxResponse();

    // And return the response which will replace the button
    // with embeddable content.
    $response->addCommand(new ReplaceCommand($selector, $content));
    return $response;
  }

}
