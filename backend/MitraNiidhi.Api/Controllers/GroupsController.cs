using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Groups;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/[controller]")]
[Authorize]
public class GroupsController(IMediator mediator) : ControllerBase
{
    // Roles are per-action: stacked [Authorize(Roles=...)] attributes AND together,
    // so a class-level Admin-only policy would block Member on ledger even if the action allows it.
    [HttpGet]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> List(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetGroupsQuery(), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Create([FromBody] CreateGroupRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new CreateGroupCommand(request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("{id:int}")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Update(int id, [FromBody] UpdateGroupRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new UpdateGroupCommand(id, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Group updated." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("{id:int}/clone")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Clone(int id, [FromBody] CloneGroupRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new CloneGroupCommand(id, request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("{id:int}/ledger")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Ledger(int id, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetGroupLedgerQuery(id), cancellationToken);
        if (!result.Succeeded)
            return NotFound(new { message = result.Error });
        return Ok(result.Data);
    }
}
