<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* ui/ssoButton.twig.html */
class __TwigTemplate_b47363451a88736c03ab96152c7445d1 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 10
        yield "
";
        // line 11
        if (($context["clientName"] ?? null)) {
            // line 12
            yield "<a target=\"_top\" class=\"login block mb-4\" href=\"";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["authURL"] ?? null), "html", null, true);
            yield "\" onclick=\"return addOAuth2LoginParams(this, event)\">
    <button type=\"button\" class=\"w-full bg-white rounded-md shadow border border-gray-500 flex items-center px-2 py-1 mb-2 text-gray-600 hover:shadow-md hover:border-blue-600 hover:text-blue-600\">
        <img class=\"w-10 h-10\" src=\"themes/";
            // line 14
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["gibbonThemeName"] ?? null), "html", null, true);
            yield "/img/";
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["service"] ?? null), "html", null, true);
            yield "-login.svg\">
        <span class=\"flex-grow text-lg\">";
            // line 15
            yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape($this->env->getFunction('__')->getCallable()("Sign in with {service}", ["service" => ($context["clientName"] ?? null)]), "html", null, true);
            yield "</span>
    </button>
</a>
";
        }
        // line 19
        yield "
<script>
function addOAuth2LoginParams(element, event)
{
    if (event) event.preventDefault();
    var googleSchoolYear = document.getElementById('gibbonSchoolYearID') ? document.getElementById('gibbonSchoolYearID').value : '';
    var googleLanguage = document.getElementById('gibboni18nID') ? document.getElementById('gibboni18nID').value : '';

    var url = element.href.replace('&options=', '&options=' + encodeURIComponent(googleSchoolYear + ':' + googleLanguage + ':'));
    window.location.href = url;
    return false;
}
</script>";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "ui/ssoButton.twig.html";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  62 => 19,  55 => 15,  49 => 14,  43 => 12,  41 => 11,  38 => 10,);
    }

    public function getSourceContext()
    {
        return new Source("", "ui/ssoButton.twig.html", "/Applications/MAMP/htdocs/chhs-testing/resources/templates/ui/ssoButton.twig.html");
    }
}
