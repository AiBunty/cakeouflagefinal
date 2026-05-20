<?php /* Cakeouflage Admin — WhatsApp Template Mappings */ ?>
<section class="section section--compact" data-page="admin-whatsapp-mappings">

  <div class="admin-page-header">
    <div>
      <h1 class="admin-page-title">Template Event Mapping</h1>
      <p class="admin-page-desc">Assign approved WhatsApp templates to business events so automated messages send correctly.</p>
    </div>
    <div class="admin-page-actions">
      <button class="btn btn--secondary" onclick="loadWaMappings()">↻ Reload</button>
    </div>
  </div>

  <article class="card">
    <div class="admin-table-header">
      <h2>Event → Template Mapping</h2>
      <span class="badge badge--info">Only approved templates are selectable</span>
    </div>
    <div class="admin-table-wrap">
      <table class="admin-table" id="waMappingsTable">
        <thead>
          <tr>
            <th>Event</th>
            <th>Mapped Template</th>
            <th>Approved Template Options</th>
            <th>Active</th>
            <th>Save</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
    <p id="waMappingStatus" class="text-muted"></p>
  </article>
</section>