<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\OakitService;
use App\Models\Landlord\OakitPlanService;
use Illuminate\Support\Str;

class OakItSeeder extends Seeder
{
    public function run(): void
    {
        // --- OAK IT Plans ---
        $basic = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'oakit-basic',
            'type' => 'oakit',
            'description' => 'For small teams getting started',
            'price_monthly' => 700,
            'price_yearly' => 7000,
            'discount_percent_yearly' => 16.67,
            'max_branches' => 1,
            'max_users_per_branch' => 5,
            'max_devices_per_branch' => 5,
            'features' => [
                'IT Support (Business Hours)',
                'Remote Monitoring',
                'Basic Security Patch Management',
                'Email & Phone Support',
                'Monthly Health Reports',
            ],
            'is_active' => true,
            'is_popular' => false,
            'highlight_color' => '#7c3aed',
            'cta_text' => 'Get Started',
            'sort_order' => 1,
        ]);

        $regular = SubscriptionPlan::create([
            'name' => 'Regular',
            'slug' => 'oakit-regular',
            'type' => 'oakit',
            'description' => 'For growing businesses',
            'price_monthly' => 1500,
            'price_yearly' => 15000,
            'discount_percent_yearly' => 16.67,
            'max_branches' => 3,
            'max_users_per_branch' => 25,
            'max_devices_per_branch' => 15,
            'features' => [
                'Everything in Basic',
                '24/7 Monitoring & Alerting',
                'Proactive Maintenance',
                'Backup Management',
                'Quarterly Business Reviews',
                'Priority Response (4hr SLA)',
            ],
            'is_active' => true,
            'is_popular' => true,
            'highlight_color' => '#2563eb',
            'cta_text' => 'Get Started',
            'sort_order' => 2,
        ]);

        $advanced = SubscriptionPlan::create([
            'name' => 'Advanced',
            'slug' => 'oakit-advanced',
            'type' => 'oakit',
            'description' => 'For enterprises needing custom solutions',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'discount_percent_yearly' => 0,
            'max_branches' => -1,
            'max_users_per_branch' => -1,
            'max_devices_per_branch' => -1,
            'features' => [
                'Everything in Regular',
                'Dedicated Account Manager',
                'Custom SLA & Response Times',
                'Strategic IT Planning',
                'Compliance Support (ISO, GDPR)',
                'On-site Support Available',
                'Custom Integrations',
            ],
            'is_active' => true,
            'is_popular' => false,
            'highlight_color' => '#65a30d',
            'cta_text' => 'Request Quote',
            'sort_order' => 3,
        ]);

        // --- OAK IT Services ---
        $servicesData = [
            ['slug' => 'automation-collaboration', 'title' => 'Automation & Collaboration', 'description' => 'Streamline workflows and team collaboration', 'icon' => 'Workflow', 'sort_order' => 1],
            ['slug' => 'ai-integrations', 'title' => 'AI Integrations', 'description' => 'Smart solutions powered by artificial intelligence', 'icon' => 'BrainCircuit', 'sort_order' => 2],
            ['slug' => 'database-data-management', 'title' => 'Database & Data Management', 'description' => 'Reliable data infrastructure and management', 'icon' => 'Database', 'sort_order' => 3],
            ['slug' => 'cybersecurity', 'title' => 'Cybersecurity', 'description' => 'Protect your business from digital threats', 'icon' => 'Shield', 'sort_order' => 4],
            ['slug' => 'software-development', 'title' => 'Software Development', 'description' => 'Custom software built for your needs', 'icon' => 'Code2', 'sort_order' => 5],
            ['slug' => 'web-applications', 'title' => 'Web Applications', 'description' => 'Modern web apps and e-commerce solutions', 'icon' => 'Globe', 'sort_order' => 6],
            ['slug' => 'content-creation-social-media', 'title' => 'Content & Social Media', 'description' => 'Content strategy and social media management', 'icon' => 'Share2', 'sort_order' => 7],
            ['slug' => 'cloud-computing', 'title' => 'Cloud Computing', 'description' => 'Scalable cloud infrastructure and migration', 'icon' => 'Cloud', 'sort_order' => 8],
            ['slug' => 'it-infrastructure', 'title' => 'IT Infrastructure', 'description' => 'Network, server, and storage deployment', 'icon' => 'Server', 'sort_order' => 9],
            ['slug' => 'email-collaboration', 'title' => 'Email & Collaboration', 'description' => 'Enterprise email and collaboration platforms', 'icon' => 'Mail', 'sort_order' => 10],
            ['slug' => 'internal-tools', 'title' => 'Internal Tools', 'description' => 'Custom tools for your team', 'icon' => 'Wrench', 'sort_order' => 11],
            ['slug' => 'remote-it-support', 'title' => 'Remote IT Support 24/7', 'description' => 'Round-the-clock IT support with SLA guarantees', 'icon' => 'Headphones', 'sort_order' => 12],
            ['slug' => 'it-training', 'title' => 'IT Training & R&D', 'description' => 'Upskill your team with hands-on training', 'icon' => 'GraduationCap', 'sort_order' => 13],
        ];

        $featuresMap = [
            'automation-collaboration' => ['Workflow Automation Solutions', 'Collaboration Tools Implementation', 'Business Process Optimization', 'RPA (Robotic Process Automation)', 'Custom Integration Development'],
            'ai-integrations' => ['AI Chatbots for Customer Support', 'Predictive Analytics Solutions', 'Machine Learning Algorithms for Data Analysis', 'Natural Language Processing (NLP)', 'AI-Powered Business Intelligence'],
            'database-data-management' => ['Database Design and Implementation', 'Database Administration and Maintenance', 'Data Warehousing Solutions', 'ETL Pipeline Development', 'Database Performance Tuning'],
            'cybersecurity' => ['Network Security Assessments', 'Firewall Configuration and Management', 'Intrusion Detection and Prevention Systems', 'Vulnerability Penetration Testing', 'Security Awareness Training'],
            'software-development' => ['Custom Software Development', 'Mobile App Development', 'Enterprise Application Development', 'API Development & Integration', 'Legacy System Modernization'],
            'web-applications' => ['Web Development Services', 'Content Management System Development', 'E-commerce Solutions Development', 'Progressive Web Apps (PWA)', 'Single Page Applications (SPA)'],
            'content-creation-social-media' => ['Content Strategy and Planning', 'Social Media Management Tools Implementation', 'Social Media Marketing Campaigns', 'Content Production & Scheduling', 'Analytics & Performance Reporting'],
            'cloud-computing' => ['Cloud Strategy Consulting', 'Cloud Migration Services', 'Infrastructure as a Service (IaaS) Provisioning', 'Platform as a Service (PaaS) Setup', 'Cloud Cost Optimization'],
            'it-infrastructure' => ['Network Design and Implementation', 'Server Installation and Configuration', 'Storage Solutions Deployment', 'Structured Cabling', 'Hardware Procurement & Setup'],
            'email-collaboration' => ['Email Hosting Services', 'Collaboration Platform Implementation', 'Document Sharing and Version Control Systems', 'Microsoft 365 / Google Workspace Setup', 'Email Security & Archiving'],
            'internal-tools' => ['Intranet Development and Deployment', 'Employee Onboarding and Training Platforms', 'Knowledge Management Systems', 'HR & Payroll System Integration', 'Custom Dashboard Development'],
            'remote-it-support' => ['Remote Troubleshooting and Issue Resolution', '24/7 Availability and Response Time SLAs', 'Proactive Monitoring and Maintenance', 'Patch Management', 'IT Asset Management'],
            'it-training' => ['IT Training and Certifications Consultation', 'IT Lab-Based Pragmatic Training', 'Project-Based and Team-Effort Training', 'Technology Workshops', 'Custom Training Programs'],
        ];

        $benefitsMap = [
            'automation-collaboration' => ['Reduce manual work by 60%+', 'Improve team productivity', 'Standardize processes across departments'],
            'ai-integrations' => ['Automate repetitive tasks', 'Gain data-driven insights', 'Enhance customer experience'],
            'database-data-management' => ['Ensure data integrity and availability', 'Optimize query performance', 'Scale with your business growth'],
            'cybersecurity' => ['Prevent costly data breaches', 'Meet compliance requirements', 'Protect customer trust'],
            'software-development' => ['Purpose-built for your workflows', 'Full ownership of code', 'Scalable architecture'],
            'web-applications' => ['Reach customers on any device', 'Fast, responsive user experience', 'SEO-friendly architecture'],
            'content-creation-social-media' => ['Grow your online presence', 'Engage your target audience', 'Drive measurable results'],
            'cloud-computing' => ['Reduce infrastructure costs', 'Scale on demand', 'Improve disaster recovery'],
            'it-infrastructure' => ['Reliable, high-performance network', 'Minimize downtime', 'Future-proof infrastructure'],
            'email-collaboration' => ['Streamline team communication', 'Centralized document management', 'Enterprise-grade security'],
            'internal-tools' => ['Boost internal efficiency', 'Centralize company knowledge', 'Reduce SaaS subscription costs'],
            'remote-it-support' => ['Minimize business disruption', 'Predictable support costs', 'Expert help anytime'],
            'it-training' => ['Close skills gaps fast', 'Hands-on, practical learning', 'Certified training programs'],
        ];

        foreach ($servicesData as $s) {
            OakitService::create([
                'slug' => $s['slug'],
                'title' => $s['title'],
                'description' => $s['description'],
                'icon' => $s['icon'],
                'features' => $featuresMap[$s['slug']],
                'benefits' => $benefitsMap[$s['slug']],
                'is_active' => true,
                'sort_order' => $s['sort_order'],
            ]);
        }

        // --- Link Services to Plans ---
        $allServices = OakitService::all();

        // Basic gets core services (5)
        $basicSlugs = ['remote-it-support', 'cybersecurity', 'it-infrastructure', 'email-collaboration', 'cloud-computing'];
        foreach ($allServices->filter(fn ($s) => in_array($s->slug, $basicSlugs)) as $service) {
            OakitPlanService::create(['plan_id' => $basic->id, 'service_id' => $service->id, 'is_included' => true]);
        }

        // Regular gets all 13 services
        foreach ($allServices as $service) {
            OakitPlanService::create(['plan_id' => $regular->id, 'service_id' => $service->id, 'is_included' => true]);
        }

        // Advanced gets all 13 services with custom limits
        foreach ($allServices as $service) {
            OakitPlanService::create(['plan_id' => $advanced->id, 'service_id' => $service->id, 'is_included' => true, 'custom_limit' => -1]);
        }
    }
}
