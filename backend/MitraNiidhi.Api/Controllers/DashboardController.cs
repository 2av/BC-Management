using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Groups;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/[controller]")]
[Authorize(Roles = "ClientAdmin,SuperAdmin")]
public class DashboardController(IMediator mediator) : ControllerBase
{
    [HttpGet("admin")]
    public async Task<IActionResult> Admin(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetDashboardStatsQuery(), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}
