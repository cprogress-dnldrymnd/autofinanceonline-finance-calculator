/**
 * Auto Finance Online Calculator Logic
 * Handles slider synchronization, UI formatting, and external API queries.
 * Incorporates polling to gracefully handle wp_localize_script deferral states.
 */

let afoInitAttempts = 0;

/**
 * Core initialization sequence for the calculator logic.
 * Enforces strict dependency checking for the globally localized afoConfig object.
 *
 * @return {void}
 */
function initializeAfoCalculator() {

    // Polling mechanism: Wait for afoConfig if async/defer optimizations altered script execution order
    if (typeof afoConfig === 'undefined') {
        afoInitAttempts++;
        if (afoInitAttempts < 40) { // Retry for up to 2 seconds
            setTimeout(initializeAfoCalculator, 50);
        } else {
            console.error('AFO Calculator FATAL: afoConfig object never initialized.');
        }
        return;
    }

    const price = parseFloat(afoConfig.price);
    const depositSlider = document.getElementById('afo-deposit');
    const borrowSlider = document.getElementById('afo-borrow');
    const termSlider = document.getElementById('afo-term');
    const quoteBtn = document.getElementById('afo-quote-btn');

    // Graceful exit if shortcode markup is absent from the current DOM
    if (!depositSlider || !borrowSlider || !termSlider) return;

    let debounceTimer;

    /**
      * Synchronizes the Deposit and Borrow sliders based on the fixed vehicle price.
      * Includes null-safety checks for programmatic bootstrapping.
      * * @param {Event|Object} e The input event object triggered by slider manipulation.
      * @return {void}
      */
    function syncSliders(e) {
        // Safely evaluate event properties to prevent null reference errors during init
        if (e && e.target && e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        } else if (e && e.target && e.target.id === 'afo-borrow') {
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
        * Includes intelligent fallback parsing for dynamic API response structures.
        * * @param {number|string} deposit The current deposit amount.
        * @param {number|string} term The repayment term in months.
        * @return {Promise<void>}
        */
    async function fetchFinanceData(deposit, term) {
        if (!afoConfig.apiKey) {
            console.error('AFO Calculator: API Key is missing from configuration.');
            return;
        }

        const baseUrl = afoConfig.apiUrl;
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

            if (!response.ok) {
                let errorMsg = `HTTP ${response.status} ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMsg = errorData.message;
                } catch (e) { }
                throw new Error(`AFO API Rejected Request: ${errorMsg}`);
            }

            const result = await response.json();

            // Check if the response payload has the expected array
            if (result.success && result.data && result.data.finance_options && result.data.finance_options.length > 0) {

                const options = result.data.finance_options;

                // Attempt to find the best tier, accounting for potential capitalization variations
                let bestOption = options.find(opt => opt.type && opt.type.toLowerCase() === 'excellent');

                // SMART FALLBACK: If 'excellent' isn't explicitly defined, grab the first available option
                if (!bestOption) {
                    bestOption = options[0];
                }

                // Push the calculated values into the DOM
                if (bestOption) {
                    // Safety check to ensure the APR exists before printing
                    const aprValue = bestOption.apr !== undefined ? bestOption.apr : '--';
                    document.getElementById('afo-res-rate').innerText = `${aprValue}% APR`;

                    document.getElementById('afo-res-credit').innerText = `£${parseFloat(bestOption.cost_of_credit).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    document.getElementById('afo-res-total').innerText = `£${parseFloat(bestOption.total_repayable).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    document.getElementById('afo-res-monthly').innerText = `£${parseFloat(bestOption.monthly_cost).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }

                /**
                 * Inject the generated tracking link into the CTA button and append dynamic UI state.
                 * Utilizes the URL interface to safely mutate query parameters before execution.
                 */
                if (result.data.referrer && result.data.referrer.link) {
                    const quoteBtn = document.getElementById('afo-quote-btn');
                    if (quoteBtn) {
                        quoteBtn.onclick = () => {
                            try {
                                // Initialize URL object from the API's base response
                                const targetUrl = new URL(result.data.referrer.link);
                                
                                // Retrieve the exact current values from the active DOM elements
                                const currentBorrow = document.getElementById('afo-borrow').value;
                                const currentDeposit = document.getElementById('afo-deposit').value;
                                
                                // Inject or overwrite the required parameters for the third-party target
                                targetUrl.searchParams.set('default-amount', currentBorrow);
                                targetUrl.searchParams.set('deposit', currentDeposit);
                                
                                // Execute the redirect with the heavily mutated URL string
                                window.open(targetUrl.toString(), '_blank');
                            } catch (urlError) {
                                console.error('AFO Link Mutation Error. Falling back to raw API string:', urlError);
                                // Failsafe: Execute raw API link if local parsing encounters an exception
                                window.open(result.data.referrer.link, '_blank');
                            }
                        };
                    }
                }
            } else {
                console.warn('AFO Calculator: API returned success, but finance_options array was missing or empty.', result);
            }
        } catch (error) {
            console.error('AFO Finance API Transport Error:', error);
        }
    }

    // Initialize Event Listeners
    depositSlider.addEventListener('input', syncSliders);
    borrowSlider.addEventListener('input', syncSliders);
    termSlider.addEventListener('input', syncSliders);

    // Bootstrap initial payload
    syncSliders({ target: null });
}

// Ensure execution bypasses standard load order restrictions
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAfoCalculator);
} else {
    initializeAfoCalculator();
}