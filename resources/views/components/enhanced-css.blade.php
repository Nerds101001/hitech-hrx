<style>
/* Enhanced Design System Styles */

/* Quick Actions Component */
.quick-action-card {
  display: block;
  padding: 1.5rem;
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  border: 1px solid #e9ecef;
  border-radius: 12px;
  text-align: center;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.quick-action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
  transform: scaleX(0);
  transition: transform 0.3s ease;
}

.quick-action-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  text-decoration: none;
  color: inherit;
}

.quick-action-card:hover::before {
  transform: scaleX(1);
}

.action-icon {
  width: 60px;
  height: 60px;
  margin: 0 auto 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  font-size: 1.5rem;
  color: white;
  transition: all 0.3s ease;
}

.quick-action-card:hover .action-icon {
  transform: scale(1.1);
  box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.action-title {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 0.5rem;
  font-size: 1rem;
}

.action-subtitle {
  font-size: 0.875rem;
  color: #6c757d;
  margin: 0;
}

/* Enhanced Hero Banner */
.admin-hero {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  padding: 2rem;
  color: white;
  position: relative;
  overflow: hidden;
}

.admin-hero::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 300px;
  height: 300px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  animation: float 6s ease-in-out infinite;
}

.admin-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.hero-icon {
  font-size: 2.5rem;
  margin-bottom: 1rem;
  opacity: 0.9;
}

.greeting {
  font-size: 2rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  line-height: 1.2;
}

.sub-text {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
}

/* Enhanced Stat Cards */
.hitech-stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  border: 1px solid #f0f0f0;
  position: relative;
  overflow: hidden;
}

.hitech-stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--card-color) 0%, var(--card-color-light) 100%);
}

.hitech-stat-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.stat-icon-wrap {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
}

/* Color Variants */
.card-teal { --card-color: #00a8a8; --card-color-light: #00d4d4; }
.card-blue { --card-color: #667eea; --card-color-light: #8b9aff; }
.card-amber { --card-color: #f59e0b; --card-color-light: #fbbf24; }
.card-red { --card-color: #ef4444; --card-color-light: #f87171; }
.card-success { --card-color: #10b981; --card-color-light: #34d399; }
.card-warning { --card-color: #f59e0b; --card-color-light: #fbbf24; }
.card-info { --card-color: #3b82f6; --card-color-light: #60a5fa; }
.card-primary { --card-color: #667eea; --card-color-light: #8b9aff; }

.icon-teal { background: linear-gradient(135deg, #00a8a8 0%, #00d4d4 100%); }
.icon-blue { background: linear-gradient(135deg, #667eea 0%, #8b9aff 100%); }
.icon-amber { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
.icon-red { background: linear-gradient(135deg, #ef4444 0%, #f87171 100%); }
.icon-success { background: linear-gradient(135deg, #10b981 0%, #34d399 100%); }
.icon-warning { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%); }
.icon-info { background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%); }
.icon-primary { background: linear-gradient(135deg, #667eea 0%, #8b9aff 100%); }

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 0.5rem;
  line-height: 1;
}

/* Enhanced Cards */
.hitech-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
  border: 1px solid #f0f0f0;
  overflow: hidden;
  transition: all 0.3s ease;
}

.hitech-card:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.hitech-card-header {
  background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
  padding: 1.5rem;
  border-bottom: 1px solid #f0f0f0;
}

.hitech-card-header .title {
  font-weight: 600;
  color: #2c3e50;
  margin: 0;
}

.card-body {
  padding: 1.5rem;
}

/* Animations */
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-20px); }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

/* Responsive Design */
@media (max-width: 768px) {
  .admin-hero-content {
    text-align: center;
  }
  
  .greeting {
    font-size: 1.5rem;
  }
  
  .sub-text {
    font-size: 1rem;
  }
  
  .quick-action-card {
    padding: 1rem;
  }
  
  .action-icon {
    width: 50px;
    height: 50px;
    font-size: 1.25rem;
  }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .hitech-card {
    background: #1a1a1a;
    border-color: #333;
  }
  
  .hitech-card-header {
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
    border-color: #333;
  }
  
  .quick-action-card {
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
    border-color: #333;
  }
  
  .action-title {
    color: #ffffff;
  }
  
  .stat-value {
    color: #ffffff;
  }
}
</style>
