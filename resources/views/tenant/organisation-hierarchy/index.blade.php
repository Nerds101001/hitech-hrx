@extends('layouts/layoutMaster')

@section('title', 'Organization Hierarchy')

@section('vendor-style')
    @vite([
        'resources/assets/vendor/scss/pages/hitech-portal.scss', 
        'resources/assets/vendor/libs/animate-css/animate.scss'
    ])
    <script src="https://d3js.org/d3.v7.min.js"></script>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
    {{-- Standardized Header --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4 py-2">
        <h3 class="fw-extrabold mb-0 text-dark">Organization Hierarchy</h3>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-white shadow-sm rounded-pill px-4 border d-flex align-items-center gap-2" id="fullscreen-toggle" title="Toggle Fullscreen">
                <i class="bx bx-fullscreen fs-5 text-muted"></i>
                <span class="fw-semibold">Fullscreen</span>
            </button>
        </div>
    </div>

    {{-- CHART SECTION --}}
    <div class="px-4">
        <div class="hitech-card-white animate__animated animate__fadeInUp overflow-hidden" style="animation-delay: 0.1s; position: relative; min-height: 700px; display: flex; flex-direction: column;">
          
          {{-- Chart Toolbar --}}
          <div class="card-body p-sm-5 p-4 border-bottom">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 w-100">
                {{-- Search & Controls --}}
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="search-wrapper-hitech w-px-350 mw-100">
                        <i class="bx bx-search text-muted ms-3 fs-5"></i>
                        <input type="text" class="form-control" placeholder="Search member, role, dept..." id="chartSearch">
                        <button class="btn-search shadow-sm" id="customSearchBtn">
                            <i class="bx bx-search fs-5"></i>
                            <span>Search</span>
                        </button>
                    </div>

                    {{-- Zoom Controls --}}
                    <div class="view-toggle-hitech shadow-sm">
                        <button class="btn-toggle" id="zoom-out" title="Zoom Out">
                            <i class="bx bx-minus"></i>
                        </button>
                        <button class="btn-toggle fw-bold" id="zoom-reset" title="Reset Zoom" style="font-size: 0.75rem; width: 50px;">
                            100%
                        </button>
                        <button class="btn-toggle" id="zoom-in" title="Zoom In">
                            <i class="bx bx-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-label-hitech rounded-pill px-4 py-2 fw-bold">
                        <i class="bx bx-broadcast me-2"></i>Interactive Live Map
                    </span>
                </div>
            </div>
          </div>

          {{-- Canvas Area --}}
          <div id="orgChartCanvas" class="flex-grow-1" style="cursor: grab; background-color: #f8fafc; overflow: hidden; position: relative;">
            @if(empty($hierarchy))
              <div class="d-flex flex-column align-items-center justify-content-center h-100 py-10">
                <div class="bg-label-primary p-4 rounded-circle mb-3">
                  <i class="bx bx-sitemap fs-1"></i>
                </div>
                <h4 class="fw-bold">No Staff Hierarchy Found</h4>
                <p class="text-muted">Please assign 'Reporting To' relationships in the employee directory to build the map.</p>
                <a href="{{ route('employees.index') }}" class="btn btn-primary rounded-pill px-5 mt-2">Go to Directory</a>
              </div>
            @endif

          {{-- Legend --}}
          <div class="chart-legend" style="position: absolute; bottom: 20px; left: 20px; z-index: 10;">
              <div class="d-flex flex-column gap-2 p-3 bg-white border rounded-4 shadow-sm">
                  <div class="d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                      <span class="dot bg-primary" style="width: 8px; height: 8px; border-radius: 50%;"></span>
                      <span class="text-muted">Manager</span>
                  </div>
                  <div class="d-flex align-items-center gap-2" style="font-size: 0.75rem;">
                      <span class="dot bg-success" style="width: 8px; height: 8px; border-radius: 50%;"></span>
                      <span class="text-muted">Staff</span>
                  </div>
              </div>
          </div>

          {{-- Loading Overlay --}}
          <div id="chartLoader" class="position-absolute top-50 start-50 translate-middle d-none">
              <div class="spinner-border text-primary" role="status"></div>
          </div>
      </div>

      {{-- Details Drawer (Rich Popover) --}}
      <div id="userCardPopover" class="details-drawer shadow-lg d-none">
          <div class="drawer-header p-4 pb-0 text-center">
              <div class="avatar-wrap mb-3 position-relative d-inline-block">
                  <img id="pop-img" src="" class="avatar-img rounded-circle border border-4 border-white shadow-sm" style="width: 80px; height: 80px;">
                  <span id="pop-status" class="status-dot online"></span>
              </div>
              <h5 id="pop-name" class="mb-0 fw-bold">...</h5>
              <div id="pop-designation" class="text-hitech small fw-semibold">...</div>
          </div>
          <div class="drawer-body p-4">
              <div class="info-list d-flex flex-column gap-3">
                  <div class="info-item d-flex align-items-center gap-3">
                      <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-building text-muted"></i></div>
                      <div class="content"><div class="label small text-muted">Department</div><div id="pop-dept" class="value fw-bold small">...</div></div>
                  </div>
                  <div class="info-item d-flex align-items-center gap-3">
                      <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-barcode-reader text-muted"></i></div>
                      <div class="content"><div class="label small text-muted">Employee Code</div><div id="pop-code" class="value fw-bold small">...</div></div>
                  </div>
                  <div class="info-item d-flex align-items-center gap-3">
                      <div class="icon-wrap bg-light rounded-pill p-2"><i class="bx bx-envelope text-muted"></i></div>
                      <div class="content"><div class="label small text-muted">Email</div><div id="pop-email" class="value fw-bold small">...</div></div>
                  </div>
              </div>
          </div>
          <div class="drawer-footer p-4 pt-0">
              <button class="btn btn-hitech-primary w-100 rounded-pill py-2" id="pop-view-profile">View Full Profile</button>
          </div>
      </div>
    </div>
</div>
@endsection
@section('page-script')
  <script>
    const hierarchyData = @json($hierarchy);
    
    document.addEventListener('DOMContentLoaded', function() {
        if (!hierarchyData || hierarchyData.length === 0) return;

        const width = document.getElementById('orgChartCanvas').offsetWidth;
        const height = 700;
        const nodeWidth = 240;
        const nodeHeight = 80;

        // Wrap the root if there are multiple roots
        const data = hierarchyData.length > 1 ? { name: "Organization", initials: "HQ", children: hierarchyData } : hierarchyData[0];

        const svg = d3.select("#orgChartCanvas")
            .append("svg")
            .attr("width", "100%")
            .attr("height", height);

        // Define SVG assets (clipping paths, etc)
        const defs = svg.append("defs");
        defs.append("clipPath")
            .attr("id", "avatarCircle")
            .append("circle")
            .attr("r", 25)
            .attr("cx", 25)
            .attr("cy", 25);

        const g = svg.append("g");
        
        svg.call(d3.zoom().scaleExtent([0.1, 3]).on("zoom", function (e) {
            g.attr("transform", e.transform);
        }));

        const tree = d3.tree()
            .nodeSize([nodeWidth + 60, nodeHeight + 100]);

        const root = d3.hierarchy(data);
        root.x0 = width / 2;
        root.y0 = 50;

        // Initials Colors
        const colors = ["#005a5a", "#10b981", "#3b82f6", "#f59e0b", "#6366f1"];

        function update(source) {
            const nodes = root.descendants();
            const links = root.links();
            tree(root);

            const duration = 750;

            // Updated Links
            const link = g.selectAll("path.link")
                .data(links, d => d.target.data.id || d.target.data.name);

            const linkEnter = link.enter().insert("path", "g")
                .attr("class", "link")
                .attr("d", d => {
                    const o = {x: source.x0, y: source.y0};
                    return d3.linkVertical().x(d => d.x).y(d => d.y)({source: o, target: o});
                })
                .attr("fill", "none")
                .attr("stroke", "#cbd5e1")
                .attr("stroke-width", 2)
                .attr("stroke-opacity", 0.4);

            link.merge(linkEnter).transition().duration(duration)
                .attr("d", d3.linkVertical().x(d => d.x).y(d => d.y));

            link.exit().transition().duration(duration).remove()
                .attr("d", d => {
                    const o = {x: source.x, y: source.y};
                    return d3.linkVertical().x(d => d.x).y(d => d.y)({source: o, target: o});
                });

            // Update Nodes
            const node = g.selectAll("g.node-group")
                .data(nodes, d => d.data.id || d.data.name);

            const nodeEnter = node.enter().append("g")
                .attr("class", "node-group")
                .attr("transform", d => `translate(${source.x0},${source.y0})`)
                .style("opacity", 0)
                .on("click", (event, d) => {
                    if (d.children) {
                        d._children = d.children;
                        d.children = null;
                    } else if (d._children) {
                        d.children = d._children;
                        d._children = null;
                    }
                    update(d);
                })
                .on("mouseenter", (e, d) => showUserCard(e, d.data))
                .on("mouseleave", hideUserCard);

            // Card
            nodeEnter.append("rect")
                .attr("x", -nodeWidth / 2)
                .attr("y", -nodeHeight / 2)
                .attr("width", nodeWidth)
                .attr("height", nodeHeight)
                .attr("rx", 16)
                .attr("fill", "#ffffff")
                .attr("stroke", "#005a5a")
                .attr("stroke-width", 1)
                .style("filter", "drop-shadow(0 4px 6px rgba(0,0,0,0.02))");

            // Profile Area (Circular fallback or image)
            const profile = nodeEnter.append("g")
                .attr("transform", `translate(${-nodeWidth / 2 + 15}, ${-nodeHeight / 2 + 15})`);

            profile.append("circle")
                .attr("r", 25)
                .attr("cx", 25)
                .attr("cy", 25)
                .attr("fill", (d, i) => colors[i % colors.length])
                .style("opacity", 0.1);

            profile.append("text")
                .attr("x", 25)
                .attr("y", 32)
                .attr("text-anchor", "middle")
                .attr("fill", (d, i) => colors[i % colors.length])
                .attr("font-size", "14px")
                .attr("font-weight", "bold")
                .text(d => d.data.initials || d.data.name.charAt(0));

            profile.append("image")
                .attr("xlink:href", d => d.data.profile_picture)
                .attr("width", 50)
                .attr("height", 50)
                .attr("clip-path", "url(#avatarCircle)")
                .on("error", function() { d3.select(this).style("display", "none"); });

            // Text Area
            nodeEnter.append("text")
                .attr("x", -nodeWidth / 2 + 75)
                .attr("y", -nodeHeight / 2 + 35)
                .attr("font-weight", "700")
                .attr("font-size", "14px")
                .attr("fill", "#1e293b")
                .text(d => d.data.name);

            nodeEnter.append("text")
                .attr("x", -nodeWidth / 2 + 75)
                .attr("y", -nodeHeight / 2 + 55)
                .attr("font-size", "12px")
                .attr("fill", "#64748b")
                .text(d => d.data.designation || "Department Root");

            // Expand/Collapse Indicator
            nodeEnter.filter(d => d.children || d._children)
                .append("circle")
                .attr("cx", 0)
                .attr("cy", nodeHeight / 2)
                .attr("r", 10)
                .attr("fill", "#005a5a")
                .attr("stroke", "white")
                .attr("stroke-width", 2);

            const nodeUpdate = node.merge(nodeEnter).transition().duration(duration)
                .attr("transform", d => `translate(${d.x},${d.y})`)
                .style("opacity", 1);

            const nodeExit = node.exit().transition().duration(duration).remove()
                .attr("transform", d => `translate(${source.x},${source.y})`)
                .style("opacity", 0);

            nodes.forEach(d => {
                d.x0 = d.x;
                d.y0 = d.y;
            });
        }

        update(root);

        // Center the initial view
        const zoom = d3.zoom().on("zoom", (e) => g.attr("transform", e.transform));
        d3.select("svg").call(zoom.transform, d3.zoomIdentity.translate(width / 2, 100).scale(0.8));

        // Tooltip logic
        const popover = document.getElementById('userCardPopover');
        function showUserCard(event, userData) {
            if(!userData.id && userData.name !== "Organization") return;
            
            document.getElementById('pop-name').innerText = userData.name || "Unknown";
            document.getElementById('pop-designation').innerText = userData.designation || "Corporate Root";
            document.getElementById('pop-dept').innerText = userData.department || "Headquarters";
            document.getElementById('pop-code').innerText = userData.code || "ORG-001";
            document.getElementById('pop-email').innerText = userData.email || "admin@company.com";
            
            const popImg = document.getElementById('pop-img');
            if (userData.profile_picture) {
                popImg.src = userData.profile_picture;
            } else {
                popImg.src = `https://ui-avatars.com/api/?name=${userData.name}&background=005a5a&color=fff`;
            }
            
            popover.classList.remove('d-none');
        }

        function hideUserCard() {
             // Optional: hide or keep visible until next hover
        }

        // Search logic
        document.getElementById('chartSearch').onkeyup = function(e) {
            const term = e.target.value.toLowerCase();
            const nodes = d3.selectAll('.node-group');
            const links = d3.selectAll('.links path');
            
            if (term === "") {
                nodes.style("opacity", 1);
                links.style("opacity", 0.4);
                return;
            }

            nodes.style("opacity", d => {
                const isMatch = d.data.name?.toLowerCase().includes(term) || 
                               d.data.designation?.toLowerCase().includes(term) ||
                               d.data.department?.toLowerCase().includes(term);
                return isMatch ? 1 : 0.1;
            });
            links.style("opacity", 0.05);
        };

        // Zoom Controls
        document.getElementById('zoom-in').onclick = () => d3.select("svg").transition().call(zoom.scaleBy, 1.3);
        document.getElementById('zoom-out').onclick = () => d3.select("svg").transition().call(zoom.scaleBy, 0.7);
        document.getElementById('zoom-reset').onclick = () => d3.select("svg").transition().call(zoom.transform, d3.zoomIdentity.translate(width / 2, 100).scale(0.8));

        // Fullscreen
        document.getElementById('fullscreen-toggle').onclick = () => {
            const canvas = document.getElementById('orgChartCanvas').parentElement;
            if (!document.fullscreenElement) {
                canvas.requestFullscreen();
            } else {
                document.exitFullscreen();
            }
        };
    });
  </script>
@endsection

@section('page-style')
  <style>
    .search-light-badge {
        background: rgba(0, 90, 90, 0.08);
        color: #005a5a;
        border: 1px solid rgba(0, 90, 90, 0.1);
    }
    .search-wrapper-hitech {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 50px !important;
        padding: 4px 4px 4px 0.5rem;
        display: flex;
        align-items: center;
        height: 50px;
        transition: all 0.3s ease;
    }
    .search-wrapper-hitech:focus-within {
        border-color: #005a5a;
        box-shadow: 0 0 0 4px rgba(0, 90, 90, 0.05);
    }
    .search-wrapper-hitech .form-control {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        height: 100% !important;
        font-size: 0.95rem;
        color: #1e293b !important;
    }
    .search-wrapper-hitech .btn-search {
        height: 40px !important;
        border-radius: 50px !important;
        background: #005a5a !important;
        color: #fff !important;
        padding: 0 1.5rem !important;
        font-weight: 600 !important;
        border: none !important;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    .search-wrapper-hitech .btn-search:hover {
        background: #004d4d !important;
        box-shadow: 0 4px 12px rgba(0, 90, 90, 0.2);
    }
    .btn-group.shadow-sm {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .btn-group.shadow-sm .btn {
        border: none !important;
        background: #fff;
        font-weight: 600;
        color: #475569;
        height: 42px;
    }
    .btn-group.shadow-sm .btn:hover {
        background: #f8fafc;
        color: #005a5a;
    }
    .btn-group.shadow-sm .btn:not(:last-child) {
        border-right: 1px solid #f1f5f9 !important;
    }
    .btn-hitech-icon {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #005a5a;
        color: white;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    /* Details Drawer */
    .details-drawer {
        position: absolute;
        top: 100px;
        right: 30px;
        width: 320px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 24px;
        z-index: 100;
        animation: slideInRight 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    
    @keyframes slideInRight {
        from { transform: translateX(50px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .status-dot {
        position: absolute;
        bottom: 5px;
        right: 5px;
        width: 15px;
        height: 15px;
        border: 3px solid #fff;
        border-radius: 50%;
    }
    .status-dot.online { background: #10b981; }

    .info-item .icon-wrap {
        width: 38px;
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* SVG Node Styling */
    .node-group rect {
        transition: all 0.3s ease;
    }
    .node-group:hover rect {
        stroke: #005a5a;
        stroke-width: 3px;
    }
    
    svg {
        user-select: none;
    }

    #orgChartCanvas:fullscreen {
        background: white;
        padding: 50px;
    }
  </style>
@endsection
