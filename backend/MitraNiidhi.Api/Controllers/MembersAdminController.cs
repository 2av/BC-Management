using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Members;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api")]
[Authorize]
public class MembersAdminController(IMediator mediator) : ControllerBase
{
    [HttpGet("members")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> List([FromQuery] string? search, [FromQuery] string? status, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetMembersQuery(search, status), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("members")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Create([FromBody] CreateMemberRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new CreateMemberCommand(request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPut("members/{id:int}")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Update(int id, [FromBody] UpdateMemberRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new UpdateMemberCommand(id, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Member updated." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("groups/{groupId:int}/members")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GroupRoster(int groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetGroupMembersQuery(groupId), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpPost("groups/{groupId:int}/members")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Assign(int groupId, [FromBody] AssignMemberRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new AssignMemberCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Member assigned." }) : BadRequest(new { message = result.Error });
    }

    [HttpDelete("groups/{groupId:int}/members/{memberId:int}")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Unassign(
        int groupId,
        int memberId,
        [FromQuery] int? groupMemberId,
        CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new UnassignMemberCommand(groupId, memberId, groupMemberId), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Member removed from group." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("groups/{groupId:int}/members/import")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Import(int groupId, [FromBody] ImportMembersRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new ImportGroupMembersCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}
