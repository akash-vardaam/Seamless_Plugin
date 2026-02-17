<div id="eventWrapper" class="seamless-content-wrapp
er">
    <!-- Main Loader for initial load -->
    <div class="loader-container">
        <div id="Seamlessloader" class="three-body">
            <div class="three-body__dot"></div>
            <div class="three-body__dot"></div>
            <div class="three-body__dot"></div>
        </div>
    </div>

    <div class="seamless-main-content" style="display: none;">
        <section class="hero-section">
            <div class="filter-form">
                <div class="filter-controls">
                    <div class="event-search-filter">
                        <label class="event-search-label">SEARCH AND FILTER</label>
                        <input type="text" placeholder="Search event by name" id="search" class="search-input" />
                        <select id="sort_by" class="sort-select">
                            <option value="all">All</option>
                            <option value="upcoming" selected>Upcoming</option>
                            <option value="current">Current</option>
                            <option value="past">Past</option>
                        </select>
                        <select id="year_filter" class="year-select">
                            <option value="">Year</option>
                        </select>

                        <input type="hidden" id="current_view" value="list" />
                        <button id="reset_btn" class="reset-button">Reset</button>
                    </div>



                    <!-- <div id="category_dropdowns" class="category-dropdowns"></div> -->

                </div>
            </div>
        </section>

        <div class="view-toggle-button-container">
            <div class="view-toggle-buttons">
                <button class="view-toggle" data-view="list">List View</button>
                <button class="view-toggle" data-view="grid">Grid View</button>
                <button class="view-toggle" data-view="calendar">Calendar View</button>
            </div>
        </div>

        <div class="details-section">
            <div id="Seamlessloader" style="display: none;">
                <div class="three-body">
                    <div class="three-body__dot"></div>
                    <div class="three-body__dot"></div>
                    <div class="three-body__dot"></div>
                </div>
            </div>
            <div class="seamless-event-list"></div>
            <div id="calendar_view" class="hidden"></div>
            <div id="pagination"></div>
        </div>
    </div>
</div>