<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;

class ArionumPlugin extends Plugin
{
  protected $route = 'arionum';

  public static function getSubscribedEvents()
  {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0],
    ];
  }

  public function onPluginsInitialized()
  {
    if (!$this->isAdmin())
      return;

    $grav = $this->grav;
    $config = $this->config;
    $twig = $grav['twig'];
    $uri = $grav['uri'];
    $pageRoute = $config->get('plugins.admin.route') . '/' . $this->route;

    $process = (strpos($uri->path(), $pageRoute) === false) ? false : true;

    $events = [
      'onAdminMenu' => ['onAdminMenu', 0],
    ];

    if ($process)
    {
      $events['onTwigTemplatePaths'] = ['onTwigAdminTemplatePaths', 0];
      $events['onTwigSiteVariables'] = ['onTwigSiteVariables', 0];
    }

    $this->enable($events);

    if (!$process)
      return;

    require_once __DIR__ . '/classes/arionum.php';

    if (isset($_GET['task']))
    {
      $aro = new Arionum\GravArionum($grav);

      if ($_GET['task'] == 'getExchanges')
      {
        $aro->getExchanges();
      }
      elseif ($_GET['task'] == 'getTransactions' && isset($_GET['address']))
      {
        $aro->getTransactions($_GET['address']);
      }
      elseif ($_GET['task'] == 'getTransaction' && isset($_GET['transaction_id']))
      {
        $aro->getTransaction($_GET['transaction_id']);
      }
      elseif ($_GET['task'] == 'getBalance' && isset($_GET['address']))
      {
        $aro->getBalance($_GET['address']);
      }
    }

    $wallets = $config->get('plugins.arionum.wallet_addresses');
    $cfgCurrencies = $config->get('plugins.arionum.currencies', []);

    $currencies = ['BTC'];

    if (count($cfgCurrencies) > 0)
    {
      foreach ($cfgCurrencies as $key => $value)
      {
        if ($value == 1)
          $currencies[] = $key;
      }
    }

    $twig->twig_vars['wallets'] = $wallets;
    $twig->twig_vars['currencies'] = $currencies;
    $twig->twig_vars['page_route'] = $this->grav['base_url'] . $pageRoute;
  }

  public function onTwigSiteVariables()
  {
    $grav = $this->grav;
    $config = $this->config;
    $pageRoute = $config->get('plugins.admin.route') . '/' . $this->route;

    $assets = $grav['assets'];
    $assets->addCss('plugin://arionum/assets/css/arionum.css');
    $assets->addJs('plugin://arionum/assets/js/arionum.js', null, 'bottom');
  }

  public function onTwigAdminTemplatePaths()
  {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
  }

  public function onAdminMenu()
  {
    $this->grav['twig']->plugins_hooked_nav['PLUGIN_ARIONUM.ARIONUM'] = [
      'route' => $this->route,
      'icon'  => 'fa-dollar'
    ];
  }
}
