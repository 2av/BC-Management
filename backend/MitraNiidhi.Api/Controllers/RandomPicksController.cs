using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.RandomPicks;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/groups/{groupId:int}/random-picks")]
[Authorize]
public class RandomPicksController(IMediator mediator) : ControllerBase
{
    [HttpGet]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> List(int groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetRandomPicksQuery(groupId), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpGet("available-members")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Available(int groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetAvailableRandomMembersQuery(groupId), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Place(int groupId, [FromBody] PlaceRandomPickRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new PlaceRandomPickCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("custom")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Custom(int groupId, [FromBody] CustomRandomPickRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new CustomRandomPickCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("{monthNumber:int}/override")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Override(
        int groupId,
        int monthNumber,
        [FromBody] OverrideRandomPickRequest request,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new OverrideRandomPickCommand(groupId, monthNumber, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Override saved." }) : BadRequest(new { message = result.Error });
    }

    [HttpDelete("{monthNumber:int}/override")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> ClearOverride(int groupId, int monthNumber, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new ClearOverrideCommand(groupId, monthNumber), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Override cleared." }) : BadRequest(new { message = result.Error });
    }
}
