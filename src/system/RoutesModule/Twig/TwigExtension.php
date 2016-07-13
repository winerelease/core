<?php
/**
 * Routes.
 *
 * @copyright Zikula contributors (Zikula)
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @author Zikula contributors <support@zikula.org>.
 * @link http://www.zikula.org
 * @link http://zikula.org
 * @version Generated by ModuleStudio 0.7.0 (http://modulestudio.de).
 */

namespace Zikula\RoutesModule\Twig;

use System;
use Zikula\ExtensionsModule\Api\VariableApi;
use Zikula\RoutesModule\Entity\RouteEntity;
use Zikula\RoutesModule\Twig\Base\TwigExtension as BaseTwigExtension;
use ZLanguage;

/**
 * Twig extension implementation class.
 */
class TwigExtension extends BaseTwigExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        $functions = parent::getFunctions();

        $functions[] = new \Twig_SimpleFunction('zikularoutesmodule_getBaseUrl', [$this, 'getBaseUrl']);

        return $functions;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        $filters = parent::getFilters();

        $filters[] = new \Twig_SimpleFilter('zikularoutesmodule_arrayToString', [$this, 'displayArrayAsString']);
        $filters[] = new \Twig_SimpleFilter('zikularoutesmodule_pathToString', [$this, 'displayPathAsString']);

        return $filters;
    }

    /**
     * The zikularoutesmodule_getBaseUrl function returns the site base url prepared for usage in JS.
     *
     * @return string The output of the plugin.
     */
    public function getBaseUrl()
    {
        $baseUrl = System::getBaseUrl();

        return rtrim($baseUrl, '/');
    }

    /**
     * The zikularoutesmodule_arrayToString filter displays the content of a given array.
     * Example:
     *    {{ route.defaults|zikularoutesmodule_arrayToString }}
     *
     * @param array $array The input array.
     *
     * @return string Output string for display.
     */
    public function displayArrayAsString(array $input = [])
    {
        return '<pre>' . print_r($input, true) . '</pre>';
    }

    /**
     * The zikularoutesmodule_pathToString filter displays a route's path.
     * Example:
     *    {{ route.path|zikularoutesmodule_pathToString(route) }}
     *
     * @param string      $path  The route path.
     * @param RouteEntity $route The route object.
     *
     * @return string Output string for display.
     */
    public function displayPathAsString($path, $route)
    {
        $prefix = '';
        $translationPrefix = $route->getTranslationPrefix();
        if (!empty($translationPrefix)) {
            $prefix = '/' . $translationPrefix;
        }

        if ($route->getTranslatable()) {
            $languages = ZLanguage::getInstalledLanguages();
            $isRequiredLangParam = ZLanguage::isRequiredLangParam();
            if (!$isRequiredLangParam) {
                $defaultLanguage = $this->variableApi->get(VariableApi::CONFIG, 'language_i18n');
                unset($languages[array_search($defaultLanguage, $languages)]);
            }
            if (count($languages) > 0) {
                $prefix = ($isRequiredLangParam ? '/' : '{/') . implode('|', $languages) . ($isRequiredLangParam ? '' : '}');
            }
        }

        $prefix = \DataUtil::formatForDisplay($prefix);
        $path = \DataUtil::formatForDisplay($route->getPathWithBundlePrefix());
        $container = \ServiceUtil::getManager();

        $path = preg_replace_callback('#%(.*?)%#', function ($matches) use ($container) {
            return '<abbr title="' . \DataUtil::formatForDisplay($matches[0]) . '">' . \DataUtil::formatForDisplay($container->getParameter($matches[1])) . '</abbr>';
        }, $path);

        $defaults = $route->getDefaults();
        $requirements = $route->getRequirements();
        $dom = ZLanguage::getModuleDomain('ZikulaRoutesModule');
        $path = preg_replace_callback('#{(.*?)}#', function ($matches) use ($container, $defaults, $requirements, $dom) {
            $title = '';
            if (isset($defaults[$matches[1]])) {
                $title .= __f('Default: %s', [\DataUtil::formatForDisplay($defaults[$matches[1]])], $dom);
            }
            if (isset($requirements[$matches[1]])) {
                if ($title != '') {
                    $title .= ' | ';
                }
                $title .= __f('Requirement: %s', [\DataUtil::formatForDisplay($requirements[$matches[1]])], $dom);
            }
            if ($title == '') {
                return $matches[0];
            }

            return '<abbr title="' . $title . '">' . $matches[0] . '</abbr>';
        }, $path);

        return $prefix . '<strong>' . $path . '</strong>';
    }
}
