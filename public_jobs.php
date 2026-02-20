<?php
require_once 'config.php';

// Get active job openings
$jobs_query = "SELECT jo.*, d.department_name, jr.title as role_title 
               FROM job_openings jo 
               JOIN departments d ON jo.department_id = d.department_id 
               JOIN job_roles jr ON jo.job_role_id = jr.job_role_id 
               WHERE jo.status = 'Open' 
               ORDER BY jo.posting_date DESC";
$jobs = $conn->query($jobs_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Job Opportunities - Apply Now</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary-color:#c82333; /* municipal red */
            --primary-dark:#8b1f28;
            --primary-light:#f7b2b8;
            --secondary-color:#0b61a4; /* municipal blue */
            --bg-card:#ffffff;
            --bg-primary:#f6f8fb;
            --bg-secondary:#eef5fb;
            --text-primary:#222;
            --text-secondary:#6c757d;
            --border-light:rgba(0,0,0,0.06);
            --shadow-light:rgba(10,10,20,0.06);
            --shadow-medium:rgba(10,10,20,0.12);
            --success:#28a745;
            --accent:var(--secondary-color);
        }
        html,body{font-family: 'Poppins', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;}
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 56px 0 40px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="white" opacity="0.1"/><circle cx="80" cy="80" r="2" fill="white" opacity="0.1"/><circle cx="40" cy="60" r="1" fill="white" opacity="0.1"/></svg>');
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            display:flex;
            align-items:center;
            gap:18px;
        }

        .municipal-logo{width:84px;height:84px;border-radius:12px;background:white;padding:8px;box-shadow:0 6px 18px rgba(0,0,0,0.12);object-fit:contain}
        
        .search-filter {
            background: var(--bg-card);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px var(--shadow-light);
            margin: -40px auto 50px;
            max-width: 900px;
            backdrop-filter: blur(10px);
        }
        
        .job-card {
            background: var(--bg-card);
            border-radius: 20px;
            box-shadow: 0 8px 30px var(--shadow-light);
            margin-bottom: 30px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-light);
            overflow: hidden;
            position: relative;
        }
        
        .job-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .job-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px var(--shadow-medium);
        }
        
        .job-header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            padding: 30px;
            border-bottom: none;
        }
        
        .job-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 12px;
            line-height: 1.3;
        }
        
        .job-meta {
            color: var(--text-secondary);
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .job-body {
            padding: 30px;
        }
        
        .job-summary {
            background: linear-gradient(135deg, var(--bg-primary) 0%, #f8f9fa 100%);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            position: relative;
        }
        
        .job-details {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .job-detail {
            background: linear-gradient(135deg, var(--primary-lighter) 0%, var(--bg-secondary) 100%);
            padding: 10px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            color: var(--primary-dark);
            font-weight: 600;
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }
        
        .job-detail:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px var(--shadow-light);
        }
        
        .modern-btn {
            border: none;
            padding: 14px 28px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .modern-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .modern-btn:hover::before {
            left: 100%;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-primary-modern:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--shadow-medium);
            color: white;
        }
        
        .btn-success-modern {
            background: linear-gradient(135deg, var(--success) 0%, #20c997 100%);
            color: white;
        }
        
        .btn-success-modern:hover {
            background: linear-gradient(135deg, #20c997 0%, var(--success) 100%);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        .filter-btn {
            background: var(--bg-card);
            border: 2px solid var(--border-light);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 25px;
            margin: 5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--shadow-light);
        }
        
        .section-title {
            text-align: center;
            color: var(--text-primary);
            font-weight: 700;
            margin: 60px 0 40px;
            font-size: 2rem;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        @media (max-width: 768px) {
            .hero h1 { font-size: 2rem; }
            .job-card { margin-bottom: 20px; }
            .job-header, .job-body { padding: 20px; }
            .section-title { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="hero">
        <div class="container hero-content">
            <img src="image/garay.jpg" alt="Municipality Logo" class="municipal-logo" onerror="this.style.display='none'">
            <div>
                <h1><i class="fas fa-city mr-2"></i>Join Our Municipal Team</h1>
                <p class="lead mb-0">Discover meaningful career opportunities in public service. Make a difference in your community.</p>
            </div>
            <div class="mt-4">
                <span class="badge badge-light mr-2 p-2" aria-live="polite"><i class="fas fa-users mr-1"></i><?php echo count($jobs); ?> Open Positions</span>
                <span class="badge badge-light p-2"><i class="fas fa-clock mr-1"></i>Apply Today</span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="search-filter">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" class="form-control" id="jobSearch" placeholder="Search by title, department, location, employment type...">
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <button class="filter-btn active" onclick="filterJobs(this,'all')" aria-pressed="true">All Jobs</button>
                    <button class="filter-btn" onclick="filterJobs(this,'Full-time')" aria-pressed="false">Full-time</button>
                    <button class="filter-btn" onclick="filterJobs(this,'Part-time')" aria-pressed="false">Part-time</button>
                    <button class="filter-btn" onclick="filterJobs(this,'Contract')" aria-pressed="false">Contract</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <h2 class="section-title">Available Positions</h2>
        
        <div class="row">
            <?php if (count($jobs) > 0): ?>
                <?php foreach($jobs as $job): ?>
                    <div class="col-lg-6">
                        <div class="job-card">
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <div class="job-meta">
                                    <i class="fas fa-building mr-2"></i><?php echo htmlspecialchars($job['department_name']); ?>
                                    <span class="ml-3"><i class="fas fa-calendar mr-2"></i>Posted: <?php echo date('M d, Y', strtotime($job['posting_date'])); ?></span>
                                </div>
                            </div>
                            <div class="job-body">
                                <div class="job-summary">
                                    <h6 class="text-primary mb-2"><i class="fas fa-info-circle mr-2"></i>Position Overview</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars(substr($job['description'], 0, 180)) . (strlen($job['description']) > 180 ? '...' : ''); ?></p>
                                </div>
                                
                                <div class="job-details">
                                    <span class="job-detail">
                                        <i class="fas fa-users mr-1"></i><?php echo $job['vacancy_count']; ?> Position<?php echo $job['vacancy_count'] > 1 ? 's' : ''; ?>
                                    </span>
                                    <span class="job-detail">
                                        <i class="fas fa-clock mr-1"></i><?php echo $job['employment_type']; ?>
                                    </span>
                                    <span class="job-detail">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($job['location']); ?>
                                    </span>
                                    <?php if ($job['salary_range_min'] && $job['salary_range_max']): ?>
                                        <span class="job-detail">
                                            <i class="fas fa-peso-sign mr-1"></i><?php echo number_format($job['salary_range_min']); ?> - <?php echo number_format($job['salary_range_max']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-3">
                                    <button class="btn modern-btn btn-primary-modern flex-fill" onclick="showJobDetails(<?php echo $job['job_opening_id']; ?>)">
                                        <i class="fas fa-eye mr-2"></i>View Details
                                    </button>
                                    <button class="btn modern-btn btn-success-modern" onclick="applyForJob(<?php echo $job['job_opening_id']; ?>)">
                                        <i class="fas fa-paper-plane mr-2"></i>Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="no-jobs">
                        <i class="fas fa-briefcase"></i>
                        <h3>No Open Positions</h3>
                        <p>There are currently no job openings available. Please check back later.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Job Details Modal -->
    <div class="modal fade" id="jobDetailsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-briefcase mr-2"></i>Job Details</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="jobDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-success" id="modalApplyBtn">
                        <i class="fas fa-paper-plane mr-2"></i>Apply for This Position
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function applyForJob(jobId) {
        fetch('clear_session.php', { method: 'POST' })
            .then(() => { window.location.href = 'apply.php?job_id=' + jobId; });
    }

    let currentFilter = 'all';

    function filterJobs(el, type) {
        currentFilter = type;
        $('.filter-btn').removeClass('active').attr('aria-pressed', 'false');
        if (el) $(el).addClass('active').attr('aria-pressed', 'true');

        $('.job-card').parent().show();
        if (type !== 'all') {
            $('.job-card').parent().each(function() {
                const found = $(this).find('.job-detail').filter(function(){
                    return $(this).text().trim().toLowerCase() === type.toLowerCase();
                }).length;
                if (!found) $(this).hide();
            });
        }
    }

    function debounce(fn, wait){ let t; return function(){ const ctx=this, args=arguments; clearTimeout(t); t=setTimeout(()=>fn.apply(ctx,args), wait); }; }

    $('#jobSearch').on('keyup', debounce(function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.job-card').parent().each(function() {
            const jobText = $(this).text().toLowerCase();
            $(this).toggle(jobText.indexOf(searchTerm) !== -1);
        });
    }, 250));

    function escapeHtml(str){ if(!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function nl2brSafe(str){ return escapeHtml(str).replace(/\n/g, '<br>'); }

    function showJobDetails(jobId) {
        const jobs = <?php echo json_encode($jobs); ?>;
        const job = jobs.find(j => parseInt(j.job_opening_id) === parseInt(jobId));

        if (!job) return;

        const posted = new Date(job.posting_date).toLocaleDateString();
        const salaryHtml = (job.salary_range_min && job.salary_range_max) ? `\n            <div class="col-md-6">\n                <div class="detail-card">\n                    <h6><i class="fas fa-peso-sign text-primary mr-2"></i>Salary Range</h6>\n                    <p>₱${parseInt(job.salary_range_min).toLocaleString()} - ₱${parseInt(job.salary_range_max).toLocaleString()}</p>\n                </div>\n            </div>` : '';

        const content = `\n            <div class="job-detail-header mb-4">\n                <h4 class="text-primary">${escapeHtml(job.title)}</h4>\n                <p class="text-muted mb-2">\n                    <i class="fas fa-building mr-2"></i>${escapeHtml(job.department_name)}\n                    <span class="ml-3"><i class="fas fa-calendar mr-2"></i>Posted: ${posted}</span>\n                </p>\n            </div>\n            <div class="row mb-4">\n                <div class="col-md-6">\n                    <div class="detail-card">\n                        <h6><i class="fas fa-users text-primary mr-2"></i>Positions Available</h6>\n                        <p>${escapeHtml(String(job.vacancy_count))} position${job.vacancy_count > 1 ? 's' : ''}</p>\n                    </div>\n                </div>\n                <div class="col-md-6">\n                    <div class="detail-card">\n                        <h6><i class="fas fa-clock text-primary mr-2"></i>Employment Type</h6>\n                        <p>${escapeHtml(job.employment_type)}</p>\n                    </div>\n                </div>\n                <div class="col-md-6">\n                    <div class="detail-card">\n                        <h6><i class="fas fa-map-marker-alt text-primary mr-2"></i>Location</h6>\n                        <p>${escapeHtml(job.location)}</p>\n                    </div>\n                </div>\n                ${salaryHtml}\n            </div>\n            <div class="mb-4">\n                <h6 class="text-primary"><i class="fas fa-info-circle mr-2"></i>Job Description</h6>\n                <div class="detail-content">${nl2brSafe(job.description)}</div>\n            </div>\n            ${job.requirements ? `\n            <div class="mb-4">\n                <h6 class="text-primary"><i class="fas fa-check-circle mr-2"></i>Requirements</h6>\n                <div class="detail-content">${nl2brSafe(job.requirements)}</div>\n            </div>` : ''}\n            ${job.responsibilities ? `\n            <div class="mb-4">\n                <h6 class="text-primary"><i class="fas fa-tasks mr-2"></i>Key Responsibilities</h6>\n                <div class="detail-content">${nl2brSafe(job.responsibilities)}</div>\n            </div>` : ''}
        `;

        document.getElementById('jobDetailsContent').innerHTML = content;
        document.getElementById('modalApplyBtn').onclick = function(){ applyForJob(jobId); };
        $('#jobDetailsModal').modal('show');
    }
    </script>
    
    <style>
    .detail-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 3px solid var(--primary-color);
    }
    
    .detail-card h6 {
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .detail-card p {
        margin: 0;
        color: #666;
    }
    
    .detail-content {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        line-height: 1.6;
        color: #555;
    }
    
    .job-detail-header {
        border-bottom: 2px solid var(--accent);
        padding-bottom: 15px;
    }
    </style>
</body>
</html>