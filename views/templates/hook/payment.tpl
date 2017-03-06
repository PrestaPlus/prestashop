<style>
    .spryng-payments-method-toggle {
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
{var_dump($configuration)}
{foreach $configuration as $name => $gateway}
    {if $gateway['enabled']}
        <p class="payment_module">
            <a
                    class="spryng_payments_payment_module"
                    title="{$gateway['title']}"
                    x-method="{$name}"
                    {if !$gateway['toggle']}
                        onclick="submitCheckout('{$name}');"
                        href="#"
                    {else}
                        x-toggle="true"
                    {/if}
            >
                {$gateway['title']} <span>{$gateway['description']}</span>
            </a>
        </p>

        {if $gateway['toggle']}
            <div id="spryng_payments_{$name}" class="spryng-payments-method-toggle" style="display: none;">
                {if $name == 'creditcard'}
                    <form id="spryng_payments_credit_card_form" method="POST" action="{$link->getModuleLink('spryngpayments', 'payment', [], true)|escape:'html'}">

                    </form>
                    <button id="spryng_cc_submit_button" class="btn btn-info">Submit</button>
                {elseif $name == 'ideal'}
                    <select name="ideal_issuer" id="ideal_issuer">
                        <option value="">Select your bank</option>
                        {foreach $gateway['issuers'] as $issuerId => $name}
                            <option value="{$issuerId}">{$name}</option>
                        {/foreach}
                    </select>
                    <button id="spryng_ideal_submit_button" class="btn btn-info">Submit</button>
                {/if}
            </div>
        {/if}
    {/if}
{/foreach}

{if $sandboxEnabled}
    <script src="https://sandbox.spryngpayments.com/cdn/jsclient.js"></script>
{else}
    <script src="https://api.spryngpayments.com/cdn/jsclient.js"></script>
{/if}
<script>
    $(document).ready(function() {
        $('.spryng_payments_payment_module').on('click', function (e) {
            if ($(this).attr('x-toggle')) { // Toggle credit card form by clicking on it
                var method = $(this).attr('x-method');
                if ($('#spryng_payments_' + method).is(':visible')) {
                    $('#spryng_payments_' + method).hide();
                }
                else {
                    $('#spryng_payments_' + method).show();
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
                cvv: $('#_cvv').val(),
                organisation: options.organisation_id,
                account: options.account_id
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

        $('#spryng_ideal_submit_button').on('click', function(e) {
            var issuer = $('#ideal_issuer').val();

            if (issuer === "" || issuer === undefined) {
                console.log('Something seems off');
                console.log(issuer);
                return false;
            }
            else {
                submitCheckout('ideal', false, issuer);
            }
        });
    });

    function submitCheckout(method, cardToken = false, issuer = false)
    {
        var formMethod = 'POST';
        var action = "{$link->getModuleLink('spryngpayments', 'payment')}";
        var params = {
            'method': method,
            'cardToken': cardToken,
            'issuer': issuer
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

        return false;
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