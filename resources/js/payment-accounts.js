import { initTableSearch } from './modules/table-search';

initTableSearch();

document.querySelectorAll('[data-payment-account-builder]').forEach((builder) => {
    const typeInputs = Array.from(builder.querySelectorAll('[data-payment-type]'));
    const accountName = builder.querySelector('#account_name');
    const fieldConfig = {
        cash: {
            typeLabel: 'Cash',
            visible: ['bank_name', 'account_number', 'account_holder_name'],
            labels: {
                bank_name: 'Cash location',
                account_number: 'Till or drawer code',
                account_holder_name: 'Custodian',
            },
            help: {
                bank_name: 'Branch, till point, drawer, petty cash, or safe location.',
                account_number: 'Internal till, drawer, safe, or cash box code.',
                account_holder_name: 'Person responsible for this cash account.',
            },
        },
        mobile_money: {
            typeLabel: 'Mobile Money',
            visible: ['bank_name', 'account_number', 'mobile_number', 'account_holder_name'],
            labels: {
                bank_name: 'Provider',
                account_number: 'Merchant or agent code',
                mobile_number: 'Wallet number',
                account_holder_name: 'Registered name',
            },
            help: {
                bank_name: 'Example: Airtel Money or TNM Mpamba.',
                account_number: 'Merchant, agent, or business short code.',
                mobile_number: 'The mobile wallet number that receives payments.',
                account_holder_name: 'Registered wallet owner or business name.',
            },
        },
        bank: {
            typeLabel: 'Bank',
            visible: ['bank_name', 'account_number', 'account_holder_name'],
            labels: {
                bank_name: 'Bank name',
                account_number: 'Account number',
                account_holder_name: 'Account holder',
            },
            help: {
                bank_name: 'Bank where money is deposited or paid from.',
                account_number: 'Bank account number.',
                account_holder_name: 'Registered bank account holder.',
            },
        },
        card: {
            typeLabel: 'Card',
            visible: ['bank_name', 'account_number', 'account_holder_name'],
            labels: {
                bank_name: 'Processor or bank',
                account_number: 'Merchant ID',
                account_holder_name: 'Settlement account',
            },
            help: {
                bank_name: 'Card processor, acquiring bank, or terminal provider.',
                account_number: 'Merchant ID, terminal ID, or settlement reference.',
                account_holder_name: 'Settlement account or business name.',
            },
        },
    };

    function valueFor(name) {
        return builder.querySelector(`[data-account-input="${name}"]`)?.value.trim() || 'Not set';
    }

    function updatePaymentAccountForm() {
        const selected = typeInputs.find((input) => input.checked)?.value || 'cash';
        const config = fieldConfig[selected] || fieldConfig.cash;

        typeInputs.forEach((input) => {
            input.closest('.payment-type-card')?.classList.toggle('active', input.checked);
        });

        builder.querySelector('[data-payment-preview-name]').textContent = accountName?.value.trim() || 'Account name';
        builder.querySelector('[data-payment-preview-type]').textContent = config.typeLabel;

        Object.keys(fieldConfig.mobile_money.labels).forEach((field) => {
            const isVisible = config.visible.includes(field);
            const wrapper = builder.querySelector(`[data-account-field="${field}"]`);
            const label = builder.querySelector(`[data-account-label="${field}"]`);
            const help = builder.querySelector(`[data-account-help="${field}"]`);
            const previewRow = builder.querySelector(`[data-payment-preview-row="${field}"]`);
            const previewLabel = builder.querySelector(`[data-payment-preview-label="${field}"]`);
            const previewValue = builder.querySelector(`[data-payment-preview-value="${field}"]`);

            if (wrapper) {
                wrapper.hidden = !isVisible;
            }

            if (previewRow) {
                previewRow.hidden = !isVisible;
            }

            if (label && config.labels[field]) {
                label.textContent = config.labels[field];
            }

            if (help && config.help[field]) {
                help.textContent = config.help[field];
            }

            if (previewLabel && config.labels[field]) {
                previewLabel.textContent = config.labels[field];
            }

            if (previewValue) {
                previewValue.textContent = valueFor(field);
            }
        });
    }

    typeInputs.forEach((input) => input.addEventListener('change', updatePaymentAccountForm));
    builder.querySelectorAll('[data-account-input], #account_name').forEach((input) => {
        input.addEventListener('input', updatePaymentAccountForm);
    });

    updatePaymentAccountForm();
});
