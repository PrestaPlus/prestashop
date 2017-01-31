{foreach $configuration as $name => $gateway}
    {if $gateway['enabled']}
        <p class="payment_module">
            <a href="{$link->getModuleLink('spryngpayments', 'payment')|escape:'html'}" title="{$gateway['title']}">
                <img src="{$this_path_bw}{$name}.jpg" alt="{$gateway['title']}" width="86" height="49"/>
                {$gateway['title']} <span>{$gateway['description']}</span>
            </a>
        </p>
    {/if}
{/foreach}