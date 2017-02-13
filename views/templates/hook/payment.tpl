<style>
    .spryng-payments-credit-card-form {
        display: block;
        margin-top: -20px;
        margin-bottom: 30px;
        padding-top: 15px;
        padding-bottom: 20px;
        padding-left: 99px;
        border: 1px solid black;
        border-top: none;
        color: #333333;
    }
</style>

{foreach $configuration as $name => $gateway}
    {if $gateway['enabled']}
        <p class="payment_module">
            <a
                    class="spryng_payments_payment_module"
                    href="{$link->getModuleLink('spryngpayments', 'payment', ['method' => $name])}"
                    title="{$gateway['title']}"
                    method="{$name}"
            >
                {$gateway['title']} <span>{$gateway['description']}</span>
            </a>
        </p>

        {if $name == 'creditcard'}
            <div id="spryng_payments_credit_card" class="spryng-payments-credit-card-form">
                <form id="spryng_payments_credit_card_form" method="POST" action="{$link->getModuleLink('spryngpayments', 'payment', [], true)|escape:'html'}">

                </form>
                <input class="btn btn-info" id="" type="submit" value="Submit Custom">
            </div>
        {/if}
    {/if}
{/foreach}

<script src="https://sandbox.spryngpayments.com/cdn/jsclient.js"></script>
<script>
    $(document).ready(function() {
        $('.spryng_payments_payment_module').on('click', function (e) {
            if ($(this).attr('method') === 'creditcard') {
                if ($('#spryng_payments_credit_card').is(':visible')) {
                    $('#spryng_payments_credit_card').hide();
                }
                else {
                    $('#spryng_payments_credit_card').show();
                }

                e.preventDefault();
            }
        });
    });

    {if $sandboxEnabled}
        var cardStore = "https://sandbox.spryngpayments.com/v1/card/";
    {else}
        var cardStore = "https://api.spryngpayments.com/v1/card/";
    {/if}

    var options = {
        cvv_placeholder_3: "123",
        card_number_placeholder: "0000 0000 0000 0000",
        submit_title: "Submit Credit Card Payment",
        payment_products: ['card'],
        cardstore_url: cardStore
    };

    jsclient.injectForm(document.getElementById('spryng_payments_credit_card_form'));
    document.getElementById('_submit_button').remove()
</script>