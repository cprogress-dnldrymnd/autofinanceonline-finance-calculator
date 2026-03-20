/**
 * Auto Finance Online Calculator Logic
 * Handles slider synchronization, UI formatting, and external API queries.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof afoConfig === 'undefined') return;

    const price = parseFloat(afoConfig.price);
    const depositSlider = document.getElementById('afo-deposit');
    const borrowSlider = document.getElementById('afo-borrow');
    const termSlider = document.getElementById('afo-term');
    const quoteBtn = document.getElementById('afo-quote-btn');

    let debounceTimer;

    /**
     * Synchronizes the Deposit and Borrow sliders based on the fixed vehicle price.
     * * @param {Event} e The input event object triggered by slider manipulation.
     * @return {void}
     */
    function syncSliders(e) {
        if (e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        } else if (e.target.id === 'afo-borrow') {
            depositSlider.value = price - parseFloat(borrowSlider.value);
        }

        // Update Text Displays with localized currency formatting
        document.getElementById('afo-display-deposit').innerText = `£${parseFloat(depositSlider.value).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('afo-display-borrow').innerText = `£${parseFloat(borrowSlider.value).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('afo-display-term').innerText = `${termSlider.value} months`;
        document.getElementById('afo-res-months').innerText = termSlider.value;

        triggerApiCall();
    }

    /**
     * Executes the API call using a debounce mechanism to optimize performance
     * and mitigate unnecessary rate limit consumption while dragging the slider.
     * * @return {void}
     */
    function triggerApiCall() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchFinanceData(depositSlider.value, termSlider.value);
        }, 500); // 500ms latency buffer
    }

    /**
      * Fetches quote details from the Auto Finance Online API.
      * Implements GET parameter construction, HTTP 429 rate limit handling,
      * and robust error parsing for potential CORS blocks.
      * * @param {number|string} deposit The current deposit amount.
      * @param {number|string} term The repayment term in months.
      * @return {Promise<void>}
      */
    async function fetchFinanceData(deposit, term) {
        if (!afoConfig.apiKey) {
            console.error('AFO Calculator: API Key is missing from configuration.');
            return;
        }

        // Dynamically assign the URL configured in the WP admin settings
        const baseUrl = afoConfig.apiUrl;

        // Construct standard URL query parameters per API specification
        const params = new URLSearchParams({
            vehicle_price: price,
            deposit: parseFloat(deposit),
            term_length: parseInt(term, 10)
        });

        try {
            const response = await fetch(`${baseUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'X-API-Key': afoConfig.apiKey,
                    'Content-Type': 'application/json'
                }
            });

            if (response.status === 429) {
                const retryAfter = response.headers.get("Retry-After") || 60;
                console.warn(`AFO API Rate limit exceeded. Halting execution for ${retryAfter} seconds.`);
                setTimeout(() => fetchFinanceData(deposit, term), retryAfter * 1000);
                return;
            }

            // Safely parse the exact API error message for debugging
            if (!response.ok) {
                let errorMsg = `HTTP ${response.status} ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMsg = errorData.message;
                } catch (e) {
                    // Suppress parsing error if response is plain text or empty (e.g., CORS block)
                }
                throw new Error(`AFO API Rejected Request: ${errorMsg}`);
            }

            const result = await response.json();

            // Enforce structure validation
            if (result.success && result.data && result.data.finance_options) {
                // Isolate the 'excellent' tier to reflect the "Best available rate" UI pattern
                const bestOption = result.data.finance_options.find(opt => opt.type === 'excellent');

                if (bestOption) {
                    document.getElementById('afo-res-rate').innerText = `${bestOption.apr}% APR`;
                    document.getElementById('afo-res-credit').innerText = `£${parseFloat(bestOption.cost_of_credit).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    document.getElementById('afo-res-total').innerText = `£${parseFloat(bestOption.total_repayable).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    document.getElementById('afo-res-monthly').innerText = `£${parseFloat(bestOption.monthly_cost).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }

                // Inject the generated tracking link into the CTA button
                if (result.data.referrer && result.data.referrer.link) {
                    quoteBtn.onclick = () => {
                        window.open(result.data.referrer.link, '_blank');
                    };
                }
            } else {
                console.warn('AFO Calculator: Unrecognized API response structure.', result);
            }

        } catch (error) {
            console.error('AFO Finance API Transport Error:', error);
            // Proactively detect network/CORS rejections
            if (error instanceof TypeError && error.message.includes('Failed to fetch')) {
                console.error('AFO Calculator Architecture Notice: This is likely a CORS block. The target server may be rejecting cross-origin requests or the custom X-API-Key header from your domain.');
            }
        }
    }

    // Initialize Event Listeners
    depositSlider.addEventListener('input', syncSliders);
    borrowSlider.addEventListener('input', syncSliders);
    termSlider.addEventListener('input', syncSliders);

    // Bootstrap initial payload
    triggerApiCall();
});