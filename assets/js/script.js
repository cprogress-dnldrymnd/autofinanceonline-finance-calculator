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
       * Handles formatting for the new 'years' slider while passing months to the results UI.
       * @param {Event|Object} e The input event object triggered by slider manipulation.
       * @return {void}
       */
    function syncSliders(e) {
        if (e && e.target && e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        } else if (e && e.target && e.target.id === 'afo-borrow') {
            depositSlider.value = price - parseFloat(borrowSlider.value);
        }

        // Extract years and convert to total months for the results display
        const termYears = parseFloat(termSlider.value);
        const termMonths = Math.round(termYears * 12);

        // Update Text Displays with localized currency and time formatting
        document.getElementById('afo-display-deposit').innerText = `£${parseFloat(depositSlider.value).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('afo-display-borrow').innerText = `£${parseFloat(borrowSlider.value).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        // Update slider label to show years
        document.getElementById('afo-display-term').innerText = `${termYears} year${termYears === 1 ? '' : 's'}`;

        // Update the results box to show the exact number of monthly payments
        document.getElementById('afo-res-months').innerText = termMonths;

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
     * Automatically converts the frontend 'years' input into the 'months' integer required by the API.
     * @param {number|string} deposit The current deposit amount.
     * @param {number|string} termYears The repayment term in years from the slider.
     * @return {Promise<void>}
     */
    async function fetchFinanceData(deposit, termYears) {
        if (!afoConfig.apiKey) {
            console.error('AFO Calculator: API Key is missing from configuration.');
            return;
        }

        const baseUrl = afoConfig.apiUrl;
        
        // Translate the frontend year decimal (e.g., 2.5) into an absolute month integer (e.g., 30)
        const termMonths = Math.round(parseFloat(termYears) * 12);

        const params = new URLSearchParams({
            vehicle_price: price,
            deposit: parseFloat(deposit),
            term_length: termMonths // API explicitly requires months
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
                setTimeout(() => fetchFinanceData(deposit, termYears), retryAfter * 1000);
                return;
            }

            if (!response.ok) {
                let errorMsg = `HTTP ${response.status} ${response.statusText}`;
                try {
                    const errorData = await response.json();
                    if (errorData.message) errorMsg = errorData.message;
                } catch (e) {}
                throw new Error(`AFO API Rejected Request: ${errorMsg}`);
            }

            const result = await response.json();

            if (result.success && result.data && result.data.finance_options && result.data.finance_options.length > 0) {
                const options = result.data.finance_options;
                let bestOption = options.find(opt => opt.type && opt.type.toLowerCase() === 'excellent');
                
                if (!bestOption) {
                    bestOption = options[0];
                }

                if (bestOption) {
                    const aprValue = bestOption.apr !== undefined ? bestOption.apr : '--';
                    document.getElementById('afo-res-rate').innerText = `${aprValue}% APR`;
                    document.getElementById('afo-res-credit').innerText = `£${parseFloat(bestOption.cost_of_credit).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('afo-res-total').innerText = `£${parseFloat(bestOption.total_repayable).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    document.getElementById('afo-res-monthly').innerText = `£${parseFloat(bestOption.monthly_cost).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                }

                if (result.data.referrer && result.data.referrer.link) {
                    const quoteBtn = document.getElementById('afo-quote-btn');
                    if (quoteBtn) {
                        quoteBtn.onclick = () => {
                            try {
                                const targetUrl = new URL(result.data.referrer.link);
                                const currentBorrow = document.getElementById('afo-borrow').value;
                                const currentDeposit = document.getElementById('afo-deposit').value;
                                
                                targetUrl.searchParams.set('default-amount', currentBorrow);
                                targetUrl.searchParams.set('deposit', currentDeposit);
                                
                                window.open(targetUrl.toString(), '_blank');
                            } catch (urlError) {
                                console.error('AFO Link Mutation Error. Falling back to raw API string:', urlError);
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