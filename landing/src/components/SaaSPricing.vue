<template>
  <section id="pricing" class="pricing-section">
    <div class="container">
      <div class="section-header">
        <span class="badge">SaaS Plans</span>
        <h2>ClassicPOS — Cloud POS Platform</h2>
        <p class="subtitle">
          Start with a <strong>3-day free trial</strong>. No credit card required.
          Cancel anytime. Full access to all features during trial.
        </p>
      </div>

      <div class="plans-grid">
        <!-- Professional Plan -->
        <div class="plan-card">
          <div class="plan-header">
            <h3>Professional</h3>
            <div class="price">
              <span class="amount">$29</span>
              <span class="period">/month</span>
            </div>
          </div>
          <div class="best-for">
            <strong>Best for:</strong> Small retail shops, bars, and restaurants with 1-5 locations.
          </div>
          <ul class="features">
            <li><span class="check">&#10003;</span> Full cloud POS system</li>
            <li><span class="check">&#10003;</span> Inventory management</li>
            <li><span class="check">&#10003;</span> Sales &amp; inventory reports</li>
            <li><span class="check">&#10003;</span> Multi-branch (up to 5 locations)</li>
            <li><span class="check">&#10003;</span> Receipt printing</li>
            <li><span class="check">&#10003;</span> Barcode scanning</li>
            <li><span class="check">&#10003;</span> Email support</li>
          </ul>
          <a :href="registerUrl('professional')" class="btn-buy">Start 3-Day Free Trial</a>
          <p class="trial-note">Then $29/mo. Cancel anytime.</p>
        </div>

        <!-- Enterprise Plan -->
        <div class="plan-card enterprise-card">
          <div class="plan-badge">Best Value</div>
          <div class="plan-header">
            <h3>Enterprise</h3>
            <div class="price">
              <span class="amount">$79</span>
              <span class="period">/month</span>
            </div>
          </div>
          <div class="best-for">
            <strong>Best for:</strong> Enterprise chains and multi-location operations needing custom integrations.
          </div>
          <ul class="features">
            <li><span class="check">&#10003;</span> Everything in Professional</li>
            <li><span class="check">&#10003;</span> Unlimited branches</li>
            <li><span class="check">&#10003;</span> Unlimited devices</li>
            <li><span class="check">&#10003;</span> Advanced analytics</li>
            <li><span class="check">&#10003;</span> Custom integrations</li>
            <li><span class="check">&#10003;</span> Priority support</li>
            <li><span class="check">&#10003;</span> SLA guarantee</li>
          </ul>
          <a :href="registerUrl('enterprise')" class="btn-buy enterprise">Start 3-Day Free Trial</a>
          <p class="trial-note">Then $79/mo. Cancel anytime.</p>
        </div>
      </div>

      <!-- Trust badges -->
      <div class="trust-badges">
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>3-day free trial</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>No credit card required</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>Cancel anytime</span>
        </div>
        <div class="badge-item">
          <span class="badge-icon">&#10003;</span>
          <span>Full feature access</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
const registerUrl = (plan) => {
  return `https://dashboard.oakitsolutionsandsupplies.com/auth/register?plan=${plan}`
}
</script>

<style scoped>
.pricing-section {
  padding: 5rem 0;
  background: #0f172a;
  color: #e2e8f0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

.section-header {
  text-align: center;
  margin-bottom: 3rem;
}

.badge {
  display: inline-block;
  padding: 0.25rem 1rem;
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  border-radius: 9999px;
  font-size: 0.875rem;
  font-weight: 500;
  margin-bottom: 1rem;
}

.section-header h2 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: #f8fafc;
}

.subtitle {
  font-size: 1.125rem;
  color: #94a3b8;
  max-width: 600px;
  margin: 0 auto;
}

.plans-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 2rem;
  margin-bottom: 3rem;
}

.plan-card {
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 1rem;
  padding: 2rem;
  position: relative;
  transition: transform 0.2s, border-color 0.2s;
}

.plan-card:hover {
  transform: translateY(-4px);
  border-color: #3b82f6;
}

.enterprise-card {
  border-color: #8b5cf6;
}

.plan-badge {
  position: absolute;
  top: -0.75rem;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, #8b5cf6, #3b82f6);
  color: white;
  padding: 0.25rem 1rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.plan-header h3 {
  font-size: 1.5rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #f8fafc;
}

.price {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
  margin-bottom: 1rem;
}

.amount {
  font-size: 2.5rem;
  font-weight: 700;
  color: #f8fafc;
}

.period {
  color: #94a3b8;
  font-size: 1rem;
}

.best-for {
  font-size: 0.875rem;
  color: #94a3b8;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #334155;
}

.features {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem 0;
}

.features li {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0;
  font-size: 0.9375rem;
  color: #cbd5e1;
}

.check {
  color: #22c55e;
  font-weight: bold;
}

.btn-buy {
  display: block;
  width: 100%;
  padding: 0.875rem 1.5rem;
  background: #3b82f6;
  color: white;
  text-align: center;
  border-radius: 0.5rem;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.2s;
}

.btn-buy:hover {
  background: #2563eb;
}

.btn-buy.enterprise {
  background: linear-gradient(135deg, #8b5cf6, #3b82f6);
}

.btn-buy.enterprise:hover {
  background: linear-gradient(135deg, #7c3aed, #2563eb);
}

.trial-note {
  text-align: center;
  font-size: 0.8125rem;
  color: #94a3b8;
  margin-top: 0.75rem;
}

.trust-badges {
  display: flex;
  justify-content: center;
  gap: 2rem;
  flex-wrap: wrap;
}

.badge-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #94a3b8;
}

.badge-icon {
  color: #22c55e;
  font-weight: bold;
}
</style>
