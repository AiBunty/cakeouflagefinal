<section class="section section--compact" data-page="event-detail" data-event-slug="<?= htmlspecialchars((string)($eventSlug ?? ''), ENT_QUOTES, 'UTF-8') ?>">
  <div class="container">
    <article class="card" style="margin-bottom:var(--space-4)">
      <p class="section-label" id="eventDetailType">Event</p>
      <h1 id="eventDetailTitle" class="section-title" style="margin:0 0 12px 0">Loading event...</h1>
      <p id="eventDetailShort" class="text-muted"></p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px">
        <p><strong>Instructor:</strong> <span id="eventDetailInstructor">-</span></p>
        <p><strong>Starts:</strong> <span id="eventDetailStartsAt">-</span></p>
        <p><strong>Status:</strong> <span id="eventDetailStatus">-</span></p>
        <p><strong>Seats:</strong> <span id="eventDetailSeats">-</span></p>
      </div>
    </article>

    <article class="card" style="margin-bottom:var(--space-4)">
      <h2>About</h2>
      <p id="eventDetailDescription" class="text-muted"></p>
      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px">
        <p><strong>Category:</strong> <span id="eventDetailCategory">-</span></p>
        <p><strong>Location:</strong> <span id="eventDetailLocation">-</span></p>
        <p><strong>Online Link:</strong> <a id="eventDetailOnlineLink" href="#" target="_blank" rel="noreferrer">-</a></p>
      </div>
    </article>

    <article class="card" id="eventDetailRegister">
      <h2>Register / Enquire</h2>
      <form id="eventDetailRegisterForm" class="form-grid" novalidate>
        <input type="hidden" name="event_slug" id="eventDetailSlugField" />
        <div class="form-row-two">
          <label class="form-control"><span>Name</span><input type="text" name="name" required /></label>
          <label class="form-control"><span>Phone</span><input type="tel" name="phone" required /></label>
        </div>
        <div class="form-row-two">
          <label class="form-control"><span>Email</span><input type="email" name="email" required /></label>
          <label class="form-control"><span>Attendees</span><input type="number" name="attendees" min="1" value="1" required /></label>
        </div>
        <label class="form-control"><span>Message</span><textarea name="message" rows="3"></textarea></label>
        <button id="eventDetailSubmitBtn" class="btn btn--primary" type="submit">Submit Registration</button>
      </form>
      <p id="eventDetailRegisterStatus" class="text-muted"></p>
    </article>
  </div>
</section>
