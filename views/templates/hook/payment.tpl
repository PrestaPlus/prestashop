{if $idealEnabled eq true}
    <p class="payment_module">
        <a href="{$link->getModuleLink('spryngpayments', 'payment')|escape:'html'}" title="{l s='Pay with iDEAL' mod='spryngpayments_ideal'}">
            <img src="{$this_path_bw}ideal.jpg" alt="{l s='Pay with iDEAL' mod='spryngpayments_ideal'}" width="86" height="49"/>
            {l s='Pay with iDEAL' mod='spryngpayments_ideal'}&nbsp;<span></span>
        </a>
    </p>
{/if}