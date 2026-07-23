using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Notifications;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/notifications")]
[Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
public class NotificationsController(IMediator mediator) : ControllerBase
{
    [HttpGet]
    public async Task<IActionResult> List([FromQuery] string? filter, [FromQuery] int page = 1, CancellationToken ct = default)
    {
        var result = await mediator.Send(new GetNotificationsQuery(filter, page), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("counts")]
    public async Task<IActionResult> Counts(CancellationToken ct)
    {
        var result = await mediator.Send(new GetNotificationCountsQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPatch("{id:int}/read")]
    public async Task<IActionResult> MarkRead(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new MarkNotificationReadCommand(id), ct);
        return result.Succeeded ? Ok(new { message = "Marked as read." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("mark-all-read")]
    public async Task<IActionResult> MarkAllRead(CancellationToken ct)
    {
        var result = await mediator.Send(new MarkAllNotificationsReadCommand(), ct);
        return result.Succeeded ? Ok(new { message = "All marked as read." }) : BadRequest(new { message = result.Error });
    }

    [HttpDelete("{id:int}")]
    public async Task<IActionResult> Delete(int id, CancellationToken ct)
    {
        var result = await mediator.Send(new DeleteNotificationCommand(id), ct);
        return result.Succeeded ? Ok(new { message = "Deleted." }) : BadRequest(new { message = result.Error });
    }
}
