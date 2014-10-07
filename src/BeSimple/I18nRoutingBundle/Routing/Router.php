<?php

namespace BeSimple\I18nRoutingBundle\Routing;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use TBN\MainBundle\Site\SiteManager;
use Symfony\Component\Routing\RequestContext;

class Router implements RouterInterface
{
    /**
     * @var SiteManager
     */
    protected $siteManager;

    /**
     * @var string
     */
    protected $subdomain;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * The locale to use when neither the parameters nor the request context
     * indicate the locale to use.
     *
     * @var string
     */
    protected $defaultLocale;

    /**
     * Constructor
     *
     * @param \Symfony\Component\Routing\RouterInterface   $router
     * @param Translator\AttributeTranslatorInterface|null $translator
     * @param string                                       $defaultLocale
     */
    public function __construct(RouterInterface $router, SiteManager $siteManager)
    {
        $this->router = $router;
        $this->siteManager = $siteManager;
        $this->subdomain = false;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param  string  $name       The name of the route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     *
     * @throws \InvalidArgumentException When the route doesn't exists
     */
    public function generate($name, $parameters = [], $absolute = false)
    {
        try {
            return $this->router->generate($name, $parameters, $absolute);
        } catch (MissingMandatoryParametersException $e) {
            if(! $this->subdomain and $this->siteManager->getCurrentSite())
            {
                $this->subdomain = $this->siteManager->getCurrentSite()->getSubdomain();
            }

            if($this->subdomain)
            {
                $parameters["subdomain"] = $this->subdomain;
                return $this->router->generate($name, $parameters, $absolute);
            }

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function match($pathinfo)
    {
        return $this->router->match($pathinfo);

       /* // if a _locale parameter isset remove the .locale suffix that is appended to each route in I18nRoute
        if (!empty($match['_locale']) && preg_match('#^(.+)\.'.preg_quote($match['_locale'], '#').'+$#', $match['_route'], $route)) {
            $match['_route'] = $route[1];

            // now also check if we want to translate parameters:
            if (null !== $this->translator && isset($match['_translate'])) {
                foreach ((array) $match['_translate'] as $attribute) {
                    $match[$attribute] = $this->translator->translate(
                        $match['_route'], $match['_locale'], $attribute, $match[$attribute]
                    );
                }
            }
        }

        return $match;*/
    }

    public function getRouteCollection()
    {
        return $this->router->getRouteCollection();
    }

    public function setContext(RequestContext $context)
    {
        $this->router->setContext($context);
    }

    public function getContext()
    {
        return $this->router->getContext();
    }

    /**
     * Overwrite the locale to be used by default if the current locale could
     * not be found when building the route
     *
     * @param string $locale
     */
    public function setDefaultLocale($locale)
    {
        $this->defaultLocale = $locale;
    }

    /**
     * Generates a I18N URL from the given parameter
     *
     * @param string   $name       The name of the I18N route
     * @param string   $locale     The locale of the I18N route
     * @param  array   $parameters An array of parameters
     * @param  Boolean $absolute   Whether to generate an absolute URL
     *
     * @return string The generated URL
     *
     * @throws RouteNotFoundException When the route doesn't exists
     */
    protected function generateI18n($name, $locale, $parameters, $absolute)
    {
        try {
            return $this->router->generate($name.'.'.$locale, $parameters, $absolute);
        } catch (RouteNotFoundException $e) {
            throw new RouteNotFoundException(sprintf('I18nRoute "%s" (%s) does not exist.', $name, $locale));
        }
    }

    /**
     * Determine the locale to be used with this request
     *
     * @param array $parameters the parameters determined by the route
     *
     * @return string
     */
    protected function getLocale($parameters)
    {
        if (isset($parameters['locale'])) {
            return $parameters['locale'];
        }

        if ($this->getContext()->hasParameter('_locale')) {
            return $this->getContext()->getParameter('_locale');
        }

        return $this->defaultLocale;
    }
}
