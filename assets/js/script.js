/**
 * Auto Finance Online Calculator Logic
 * Refactored for the 2-column Finance Example layout.
 * Strictly scoped to map only the original data points.
 */

let afoInitAttempts = 0;

/**
 * Initializes the primary state of the calculator.
 * Ensures DOM is fully parsed and backend configurations are injected before binding logic.
 *
 * @return {void}
 */
function initializeAfoCalculator() {

    if (typeof afoConfig === 'undefined') {
        afoInitAttempts++;
        if (afoInitAttempts < 40) {
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

    if (!depositSlider || !borrowSlider || !termSlider) return;

    let debounceTimer;

    /**
     * Dynamically updates the position of the floating tooltips above the sliders.
     * @param {HTMLInputElement} slider The active range input element.
     * @param {string} tooltipId The DOM ID of the target tooltip to translate.
     * @return {void}
     */
    function updateTooltipPosition(slider, tooltipId) {
        const tooltip = document.getElementById(tooltipId);
        if (!tooltip) return;
        
        const min = parseFloat(slider.min) || 0;
        const max = parseFloat(slider.max) || 100;
        const val = parseFloat(slider.value);
        
        const percentage = ((val - min) / (max - min)) * 100;
        tooltip.style.left = `calc(${percentage}%)`;
    }

    /**
     * Synchronizes the invisible 'borrow' mathematically with the user's deposit, updates UI, and triggers API.
     * @param {Event|Object} e The input event object.
     * @return {void}
     */
    function syncSliders(e) {
        if (e && e.target && e.target.id === 'afo-deposit') {
            borrowSlider.value = price - parseFloat(depositSlider.value);
        }

        const currentBorrow = parseFloat(borrowSlider.value);
        const exactDeposit = price - currentBorrow;
        const termYears = parseFloat(termSlider.value);
        const termMonths = Math.round(termYears * 12);

        // Update Tooltip Text and Positioning
        document.getElementById('afo-tooltip-deposit').innerText = `£${exactDeposit.toLocaleString('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
        updateTooltipPosition(depositSlider, 'afo-tooltip-deposit');

        document.getElementById('afo-tooltip-term').innerText = `${termYears} year${termYears === 1 ? '' : 's'}`;
        updateTooltipPosition(termSlider, 'afo-tooltip-term');

        // Update Rep Example placeholders immediately based on the current mathematical state
        document.getElementById('rep-price').innerText = `£${price.toLocaleString('en-GB')}`;
        document.getElementById('rep-deposit').innerText = `£${exactDeposit.toLocaleString('en-GB')}`;
        document.getElementById('afo-res-borrowing').innerText = `£${currentBorrow.toLocaleString('en-GB')}`;
        document.getElementById('afo-res-plan-months').innerText = termMonths;

        triggerApiCall(exactDeposit, termYears);
    }

    /**
     * Stepper functionality for the < and > increment buttons.
     */
    document.querySelectorAll('.afo-step-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const targetId = e.target.getAttribute('data-target');
            const direction = parseInt(e.target.getAttribute('data-dir'));
            const slider = document.getElementById(targetId);
            
            if (slider) {
                const step = parseFloat(slider.step) || 1;
                let newValue = parseFloat(slider.value) + (step * direction);
                
                if (newValue < parseFloat(slider.min)) newValue = slider.min;
                if (newValue > parseFloat(slider.max)) newValue = slider.max;
                
                slider.value = newValue;
                syncSliders({ target: slider });
            }
        });
    });

    /**
     * Debounces the API call to mitigate rate limits during fast slider manipulation.
     * @param {number} exactDeposit 
     * @param {number} termYears 
     */
    function triggerApiCall(exactDeposit, termYears) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchFinanceData(exactDeposit, termYears);
        }, 500); 
    }

    /**
     * Fetches quote details from the Auto Finance Online API.
     * Maps the response payload strictly to the original DOM elements requested.
     * @param {number|string} deposit 
     * @param {number|string} termYears 
     * @return {Promise<void>}
     */
    async function fetchFinanceData(deposit, termYears) {
        if (!afoConfig.apiKey) return;

        const baseUrl = afoConfig.apiUrl;
        const termMonths = Math.round(parseFloat(termYears) * 12);
        const params = new URLSearchParams({
            vehicle_price: price,
            deposit: parseFloat(deposit),
            term_length: termMonths
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
                setTimeout(() => fetchFinanceData(deposit, termYears), retryAfter * 1000);
                return;
            }

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const result = await response.json();

            if (result.success && result.data && result.data.finance_options && result.data.finance_options.length > 0) {
                const options = result.data.finance_options;
                let bestOption = options.find(opt => opt.type && opt.type.toLowerCase() === 'excellent');
                if (!bestOption) bestOption = options[0];

                if (bestOption) {
                    const aprValue = bestOption.apr !== undefined ? bestOption.apr : '--';
                    
                    // Format numeric values
                    const monthly = parseFloat(bestOption.monthly_cost).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const total = parseFloat(bestOption.total_repayable).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    const interest = parseFloat(bestOption.cost_of_credit).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                    // Output to DOM elements (scoped to original requirements)
                    document.getElementById('afo-res-monthly').innerText = `£${monthly}`;
                    document.getElementById('afo-res-total').innerText = `£${total}`;
                    document.getElementById('afo-res-rate').innerText = `${aprValue}% APR`;
                    
                    const interestEl = document.getElementById('afo-res-credit');
                    if (interestEl) interestEl.innerText = `£${interest}`;
                }

                if (result.data.referrer && result.data.referrer.link) {
                    const btn = document.getElementById('afo-quote-btn');
                    if (btn) {
                        btn.onclick = () => {
                            try {
                                const targetUrl = new URL(result.data.referrer.link);
                                targetUrl.searchParams.set('default-amount', document.getElementById('afo-borrow').value);
                                targetUrl.searchParams.set('deposit', document.getElementById('afo-deposit').value);
                                window.open(targetUrl.toString(), '_blank');
                            } catch (e) {
                                window.open(result.data.referrer.link, '_blank');
                            }
                        };
                    }
                }
            }
        } catch (error) {
            console.error('AFO Finance API Transport Error:', error);
        }
    }

    depositSlider.addEventListener('input', syncSliders);
    termSlider.addEventListener('input', syncSliders);

    // Initial Bootstrap
    syncSliders({ target: depositSlider });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAfoCalculator);
} else {
    initializeAfoCalculator();
}