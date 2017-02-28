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
            <div id="spryng_payments_credit_card" class="spryng-payments-credit-card-form" style="display: none;">
                <form id="spryng_payments_credit_card_form" method="POST" action="{$link->getModuleLink('spryngpayments', 'payment', [], true)|escape:'html'}">

                </form>
                <button id="spryng_cc_submit_button" class="btn btn-info">Submit</button>
            </div>
        {/if}
    {/if}
{/foreach}

<script src="https://sandbox.spryngpayments.com/cdn/jsclient.js"></script>
<script>
    $(document).ready(function() {
        $('.spryng_payments_payment_module').on('click', function (e) {
            if ($(this).attr('method') === 'creditcard') { // Toggle credit card form by clicking on it
                if ($('#spryng_payments_credit_card').is(':visible')) {
                    $('#spryng_payments_credit_card').hide();
                }
                else {
                    $('#spryng_payments_credit_card').show();
                }

                e.preventDefault();
            }
            else {
                submitCheckout($(this).attr('method'));
            }
        });

        $('#spryng_cc_submit_button').on('click', function(e) {
            var cc = {
                card_number: $('#_card_number').val(),
                expiry_month: $('#_expiry').val().split('/')[0],
                expiry_year: $('#_expiry').val().split('/')[1],
                cvv: $('#_cvv').val()
            };

            {if $sandboxEnabled}
            var cardStore = "https://sandbox.spryngpayments.com/v1/card/";
            {else}
            var cardStore = "https://api.spryngpayments.com/v1/card/";
            {/if}

            $.ajax({
                url: cardStore,
                method: 'POST',
                data: cc,
                success: function (response) {
                    submitCheckout('creditcard', response._id);
                }
            });
        });
    });

    function submitCheckout(method, cardToken = false)
    {
        var formMethod = 'POST';
        var action = "{$link->getModuleLink('spryngpayments', 'payment')}";
        var params = {
            'method': method,
            'cardToken': cardToken
        };

        var form = document.createElement('form');
        form.setAttribute('method', formMethod);
        form.setAttribute('action', action);

        for (var param in params) {
            var input = document.createElement('input');
            input.setAttribute('type', 'hidden');
            input.setAttribute('name', param);
            input.setAttribute('value', params[param]);

            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
    {if $configuration['creditcard']['enabled']}
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
            cardstore_url: cardStore,
            organisation_id: "{$configuration['creditcard']['organisation']}",
            account_id: "{$configuration['creditcard']['account']}"
        };

        jsclient.injectForm(document.getElementById('spryng_payments_credit_card_form'), options);
        document.getElementById('_submit_button').remove();
    {/if}
</script>